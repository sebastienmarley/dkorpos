<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use Database\Factories\CustomerOrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $customer_id
 * @property CustomerOrderStatus $status
 * @property float $subtotal
 * @property float $gst
 * @property float $qst
 * @property float $total
 * @property float $amount_paid
 * @property float $balance_due
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read customer $customer
 * @property-read User|null $creator
 * @property-read Collection<int, User> $salespeople
 * @property-read Collection<int, CustomerOrderLine> $lines
 * @property-read Collection<int, CustomerOrderPickup> $pickups
 * @property-read Collection<int, CustomerOrderPayment> $payments
 */
#[Fillable(['customer_id', 'status', 'subtotal', 'gst', 'qst', 'total', 'amount_paid', 'balance_due', 'created_by'])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'balance_due' => 0,
    ];

    protected $casts = [
        'status' => CustomerOrderStatus::class,
        'balance_due' => 'float',
        'subtotal' => 'float',
        'gst' => 'float',
        'qst' => 'float',
        'total' => 'float',
        'amount_paid' => 'float',
    ];

    /** @return BelongsTo<customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Vendeurs de la commande, avec leur part de la vente (pivot `percent`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function salespeople(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_order_salesperson')
            ->withPivot('percent')
            ->withTimestamps();
    }

    /** @return HasMany<CustomerOrderPickup, $this> */
    public function pickups(): HasMany
    {
        return $this->hasMany(CustomerOrderPickup::class);
    }

    /** @return HasMany<CustomerOrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerOrderPayment::class);
    }

    /** @return HasMany<CustomerOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    /**
     * Ajoute le produit à la commande : la quantité est d'abord réservée dans le stock disponible, le reste
     * est mis en commande sur le brouillon de commande fournisseur. La ligne modifiable existante du produit est
     * augmentée, sinon une ligne est créée au prix vendant.
     *
     * @throws DomainException si une quantité doit être commandée et que le produit ne peut pas l'être.
     */
    public function addProduct(Product $product, int $quantity = 1): CustomerOrderLine
    {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        return DB::transaction(function () use ($product, $quantity): CustomerOrderLine {
            $line = $this->lines()
                ->where('product_id', $product->id)
                ->whereIn('status', [CustomerOrderLineStatus::InStock, CustomerOrderLineStatus::OnOrder])
                ->lockForUpdate()
                ->first()
                ?? $this->lines()->make(['product_id' => $product->id, 'unit_price' => $product->selling_price]);

            $available = InventoryStock::query()->lockForUpdate()->where('product_id', $product->id)->value('quantity_in_stock') ?? 0;
            $reserved = min((int) $available, $quantity);

            if ($reserved > 0) {
                $this->moveReservation($product->id, InventoryStatus::InStock, InventoryStatus::ReservedCustomer, $reserved);
            }

            $this->fillLineQuantities($line, $line->quantity_reserved + $reserved, $line->quantity_on_order + $quantity - $reserved);
            $line->save();

            $this->syncSupplierLine($line);
            $this->recalculateBalance();

            return $line;
        });
    }

    /**
     * Ajuste la répartition d'une ligne entre le stock réservé et la partie en commande. Le stock libéré
     * redevient disponible pour les autres clients; toute augmentation de la réservation vérifie d'abord
     * que le stock est encore disponible. La ligne de commande fournisseur suit la partie en commande.
     *
     * @throws DomainException si la ligne n'est plus modifiable ou si le stock est insuffisant.
     */
    public function adjustLine(CustomerOrderLine $line, int $reserved, int $onOrder): void
    {
        if ($reserved < 0 || $onOrder < 0 || $reserved + $onOrder < 1) {
            throw new DomainException(__('La quantité totale de la ligne doit être d\'au moins 1.'));
        }

        DB::transaction(function () use ($line, $reserved, $onOrder): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->status->isEditable()) {
                throw new DomainException(__('Cette ligne ne peut plus être modifiée.'));
            }

            $difference = $reserved - $line->quantity_reserved;

            if ($difference > 0) {
                $this->moveReservation($line->product_id, InventoryStatus::InStock, InventoryStatus::ReservedCustomer, $difference);
            } elseif ($difference < 0) {
                $this->moveReservation($line->product_id, InventoryStatus::ReservedCustomer, InventoryStatus::InStock, -$difference);
            }

            $this->fillLineQuantities($line, $reserved, $onOrder);
            $line->save();

            $this->syncSupplierLine($line);
            $this->recalculateBalance();
        });
    }

    /**
     * Répercute sur une ligne la quantité modifiée de sa ligne de commande fournisseur : la quantité « en commande »
     * (ce qui reste à recevoir, et donc la quantité totale vendue) suit; le statut et le stock réservé ne changent pas.
     */
    public function applySupplierQuantity(CustomerOrderLine $line, int $onOrder): void
    {
        DB::transaction(function () use ($line, $onOrder): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if ($line->quantity_on_order === $onOrder) {
                return;
            }

            $line->update([
                'quantity_on_order' => $onOrder,
                'quantity' => $line->quantity_reserved + $onOrder,
            ]);

            $this->recalculateBalance();
        });
    }

    /**
     * Répercute une réception de marchandise : les unités reçues sont aussitôt réservées au client (elles ne sont
     * pas disponibles pour les autres) et passent de « en commande » à « en stock » sur la ligne. Une fois tout reçu,
     * la ligne passe à « Reçu ».
     */
    public function applySupplierReceipt(CustomerOrderLine $line, int $quantity): void
    {
        DB::transaction(function () use ($line, $quantity): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);
            $quantity = min($quantity, $line->quantity_on_order);

            if ($quantity < 1) {
                return;
            }

            $this->moveReservation($line->product_id, InventoryStatus::InStock, InventoryStatus::ReservedCustomer, $quantity);

            $onOrder = $line->quantity_on_order - $quantity;

            $line->update([
                'quantity_reserved' => $line->quantity_reserved + $quantity,
                'quantity_on_order' => $onOrder,
                'status' => $onOrder === 0 ? CustomerOrderLineStatus::Received : CustomerOrderLineStatus::Ordered,
            ]);
        });
    }

    /**
     * Répercute le renversement d'une réception faite par erreur : la réservation des unités est libérée (le
     * fournisseur les remet ensuite « en commande ») et la ligne redevient « Commandé ».
     *
     * @throws DomainException si la marchandise est déjà sortie pour le client.
     */
    public function applySupplierReceiptReversal(CustomerOrderLine $line, int $quantity): void
    {
        DB::transaction(function () use ($line, $quantity): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! in_array($line->status, [CustomerOrderLineStatus::Ordered, CustomerOrderLineStatus::Received], true) || $line->quantity_reserved < $quantity) {
                throw new DomainException(__('La marchandise de la commande client #:id est déjà sortie : la réception ne peut plus être renversée.', ['id' => $this->id]));
            }

            $this->moveReservation($line->product_id, InventoryStatus::ReservedCustomer, InventoryStatus::InStock, $quantity);

            $line->update([
                'quantity_reserved' => $line->quantity_reserved - $quantity,
                'quantity_on_order' => $line->quantity_on_order + $quantity,
                'status' => CustomerOrderLineStatus::Ordered,
            ]);
        });
    }

    /**
     * Répercute l'annulation par le fournisseur de ce qui reste à recevoir sur une ligne. Le stock réservé (y compris
     * ce qui est déjà reçu) est conservé; une ligne sans réservation ni réception est annulée.
     */
    public function applySupplierCancellation(CustomerOrderLine $line, int $quantityReceived): void
    {
        DB::transaction(function () use ($line, $quantityReceived): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if ($line->quantity_reserved === 0) {
                $line->update(['status' => CustomerOrderLineStatus::Cancelled]);
            } else {
                $line->update([
                    'quantity_on_order' => 0,
                    'quantity' => $line->quantity_reserved,
                    'status' => $quantityReceived > 0 ? CustomerOrderLineStatus::Received : CustomerOrderLineStatus::InStock,
                    'supplier_order_line_id' => $quantityReceived > 0 ? $line->supplier_order_line_id : null,
                ]);
            }

            $this->recalculateBalance();
        });
    }

    /**
     * Répercute une substitution fournisseur : ce qui reste à recevoir passe sur le produit de remplacement, au prix
     * vendant de ce produit. Sans stock réservé ni réception, la ligne change simplement de produit; sinon elle garde
     * sa partie réservée ou reçue et une nouvelle ligne « Commandé » est créée pour le substitut.
     */
    public function applySupplierSubstitution(CustomerOrderLine $line, int $quantityReceived, SupplierOrderLine $replacement): CustomerOrderLine
    {
        return DB::transaction(function () use ($line, $quantityReceived, $replacement): CustomerOrderLine {
            $line = $this->lines()->with('product')->lockForUpdate()->findOrFail($line->id);
            $sellingPrice = Product::query()->with('supplier')->findOrFail($replacement->product_id)->selling_price;

            $note = __('Substitut fournisseur de :model.', ['model' => $line->product->model]);

            if ($quantityReceived === 0 && $line->quantity_reserved === 0) {
                $line->update([
                    'product_id' => $replacement->product_id,
                    'supplier_order_line_id' => $replacement->id,
                    'quantity_on_order' => $replacement->quantity,
                    'quantity' => $replacement->quantity,
                    'unit_price' => $sellingPrice,
                    'note' => trim($line->note."\n".$note),
                ]);

                $this->recalculateBalance();

                return $line;
            }

            $line->update([
                'quantity_on_order' => 0,
                'quantity' => $line->quantity_reserved,
                'status' => $quantityReceived > 0 ? CustomerOrderLineStatus::Received : CustomerOrderLineStatus::InStock,
                'supplier_order_line_id' => $quantityReceived > 0 ? $line->supplier_order_line_id : null,
            ]);

            $substitute = $this->lines()->create([
                'product_id' => $replacement->product_id,
                'supplier_order_line_id' => $replacement->id,
                'quantity_reserved' => 0,
                'quantity_on_order' => $replacement->quantity,
                'quantity' => $replacement->quantity,
                'unit_price' => $sellingPrice,
                'status' => CustomerOrderLineStatus::Ordered,
                'note' => $note,
            ]);

            $this->recalculateBalance();

            return $substitute;
        });
    }

    /**
     * Met à jour une ligne depuis le modal d'édition : répartition stock réservé / en commande et prix vendant
     * tant que la ligne est modifiable, note en tout temps.
     *
     * @throws DomainException si la ligne n'est plus modifiable (quantités ou prix changés) ou si le stock est insuffisant.
     */
    public function updateLine(CustomerOrderLine $line, int $reserved, int $onOrder, float $unitPrice, ?string $note): void
    {
        DB::transaction(function () use ($line, $reserved, $onOrder, $unitPrice, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            $changesSale = $reserved !== $line->quantity_reserved
                || $onOrder !== $line->quantity_on_order
                || round($unitPrice, 2) !== round($line->unit_price, 2);

            if ($changesSale) {
                if (! $line->status->isEditable()) {
                    throw new DomainException(__('Les quantités et le prix de cette ligne ne peuvent plus être modifiés.'));
                }

                $this->adjustLine($line, $reserved, $onOrder);
                $line->refresh();
            }

            $line->update([
                'unit_price' => round($unitPrice, 2),
                'note' => filled($note) ? $note : null,
            ]);

            $this->recalculateBalance();
        });
    }

    /**
     * Retire une ligne modifiable : son stock réservé redevient disponible et sa ligne de commande fournisseur
     * (non envoyée) est supprimée.
     *
     * @throws DomainException si la ligne n'est plus modifiable.
     */
    public function removeLine(CustomerOrderLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->status->isEditable()) {
                throw new DomainException(__('Cette ligne ne peut plus être retirée.'));
            }

            if ($line->quantity_reserved > 0) {
                $this->moveReservation($line->product_id, InventoryStatus::ReservedCustomer, InventoryStatus::InStock, $line->quantity_reserved);
            }

            $this->detachSupplierLine($line);
            $line->delete();

            $this->recalculateBalance();
        });
    }

    /**
     * Fait suivre la quantité « en commande » d'une ligne par sa ligne de commande fournisseur : ajoutée au
     * brouillon du fournisseur du produit (créé au besoin), ajustée, ou supprimée quand plus rien n'est à commander.
     *
     * @throws DomainException si le produit ne peut pas être commandé ou si la commande fournisseur est déjà envoyée.
     */
    private function syncSupplierLine(CustomerOrderLine $line): void
    {
        if ($line->quantity_on_order === 0) {
            $this->detachSupplierLine($line);

            return;
        }

        $supplierLine = $line->supplierOrderLine()->with('order')->first();

        if ($supplierLine !== null) {
            if ($supplierLine->quantity !== $line->quantity_on_order) {
                $this->guardSupplierLineEditable($supplierLine);
                $supplierLine->order->updateLine($supplierLine, $line->quantity_on_order, $supplierLine->unit_cost);
            }

            return;
        }

        $product = $line->product()->with('supplier')->firstOrFail();

        if ($product->is_non_orderable || ! $product->supplier->is_active || ! $product->supplier->orderable || $product->supplier->type !== SupplierType::Product) {
            throw new DomainException(__('Le produit :model ne peut pas être commandé chez :supplier : il faut le prendre en stock.', [
                'model' => $product->model,
                'supplier' => $product->supplier->name,
            ]));
        }

        $draft = SupplierOrder::query()
            ->where('supplier_id', $product->supplier_id)
            ->where('status', SupplierOrderStatus::Draft)
            ->lockForUpdate()
            ->first()
            ?? SupplierOrder::create([
                'type' => SupplierType::Product,
                'supplier_id' => $product->supplier_id,
                'created_by' => auth()->id(),
            ]);

        $supplierLine = $draft->addLine([
            'product_id' => $product->id,
            'quantity' => $line->quantity_on_order,
            'unit_cost' => $product->cost,
        ]);

        $line->update(['supplier_order_line_id' => $supplierLine->id]);
    }

    /**
     * Supprime la ligne de commande fournisseur liée (seulement si la commande fournisseur n'est pas envoyée).
     */
    private function detachSupplierLine(CustomerOrderLine $line): void
    {
        $supplierLine = $line->supplierOrderLine()->with('order')->first();

        if ($supplierLine === null) {
            return;
        }

        $this->guardSupplierLineEditable($supplierLine);

        $line->update(['supplier_order_line_id' => null]);
        $supplierLine->delete();
    }

    private function guardSupplierLineEditable(SupplierOrderLine $supplierLine): void
    {
        if (! $supplierLine->order->status->isEditable()) {
            throw new DomainException(__('La commande fournisseur :number est déjà envoyée : la quantité commandée ne peut plus être modifiée ici.', [
                'number' => $supplierLine->order->number,
            ]));
        }
    }

    private function fillLineQuantities(CustomerOrderLine $line, int $reserved, int $onOrder): void
    {
        $line->fill([
            'quantity_reserved' => $reserved,
            'quantity_on_order' => $onOrder,
            'quantity' => $reserved + $onOrder,
            'status' => $onOrder > 0 ? CustomerOrderLineStatus::OnOrder : CustomerOrderLineStatus::InStock,
        ]);
    }

    private function moveReservation(int $productId, InventoryStatus $from, InventoryStatus $to, int $quantity): void
    {
        InventoryMovement::record(
            $productId,
            $from,
            $to,
            $quantity,
            $to === InventoryStatus::ReservedCustomer ? InventoryMovementType::CustomerReservation : InventoryMovementType::CustomerReservationReleased,
            $this,
        );
    }

    /**
     * Recalcule les totaux : sous-total des lignes facturables, TPS, TVQ, total, montant payé et solde à payer.
     */
    public function recalculateBalance(): void
    {
        $subtotal = round($this->lines()->get()
            ->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable())
            ->sum(fn (CustomerOrderLine $line): float => $line->total), 2);
        $taxes = self::taxesFor($subtotal);
        $amountPaid = round((float) $this->payments()->sum('amount'), 2);

        $this->update([
            'subtotal' => $subtotal,
            'gst' => $taxes['gst'],
            'qst' => $taxes['qst'],
            'total' => $taxes['total'],
            'amount_paid' => $amountPaid,
            'balance_due' => round($taxes['total'] - $amountPaid, 2),
        ]);
    }

    /**
     * TPS et TVQ calculées chacune sur le montant avant taxes, arrondies au cent.
     *
     * @return array{gst: float, qst: float, total: float}
     */
    public static function taxesFor(float $subtotal): array
    {
        $gst = round($subtotal * config('sales.taxes.gst') / 100, 2);
        $qst = round($subtotal * config('sales.taxes.qst') / 100, 2);

        return ['gst' => $gst, 'qst' => $qst, 'total' => round($subtotal + $gst + $qst, 2)];
    }

    /**
     * Montant à encaisser pour remettre ces quantités au client : les articles remis (déjà ou maintenant) doivent
     * être payés à 100 % et le reste de la commande couvert par le dépôt minimum, taxes incluses, moins ce qui est
     * déjà payé.
     *
     * @param  array<int, int>  $quantities  quantité à remettre par ligne
     */
    public function amountRequiredFor(array $quantities): float
    {
        $lines = $this->lines()->get()->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable());

        $handedOver = $lines->filter(fn (CustomerOrderLine $line): bool => $line->status->isHandedOver())
            ->sum(fn (CustomerOrderLine $line): float => $line->total)
            + $lines->sum(fn (CustomerOrderLine $line): float => ($quantities[$line->id] ?? 0) * $line->unit_price);
        $handedOver = round($handedOver, 2);
        $remaining = round($lines->sum(fn (CustomerOrderLine $line): float => $line->total) - $handedOver, 2);

        $required = self::taxesFor($handedOver)['total']
            + self::taxesFor($remaining)['total'] * config('sales.deposit_percent') / 100;

        $this->refresh();

        return max(0.0, min(round($required - $this->amount_paid, 2), $this->balance_due));
    }

    /**
     * Remet au client les quantités choisies (prises dans le stock réservé) et encaisse le paiement exigé.
     * Une ligne remise en partie est séparée : la partie ramassée devient une ligne « Ramassé ». Les unités
     * sortent de l'inventaire (premier entré, premier sorti). Sans article, seul le paiement est encaissé
     * (aucun ramassage n'est créé).
     *
     * @param  array<int, int>  $quantities  quantité à remettre par ligne
     * @param  array<int, array{method_id: int, amount: float}>  $payments
     *
     * @throws DomainException si une quantité n'est pas disponible ou si le paiement ne couvre pas le montant exigé.
     */
    public function pickUp(array $quantities, array $payments): ?CustomerOrderPickup
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);

        if ($quantities === [] && round(array_sum(array_column($payments, 'amount')), 2) <= 0) {
            throw new DomainException(__('Choisissez au moins un article à ramasser ou entrez un paiement.'));
        }

        return DB::transaction(function () use ($quantities, $payments): ?CustomerOrderPickup {
            $lines = $this->lines()->lockForUpdate()->whereKey(array_keys($quantities))->get()->keyBy('id');

            foreach ($quantities as $lineId => $quantity) {
                $line = $lines->get($lineId);

                if ($line === null || ! $line->status->isPickable() || $quantity > $line->quantity_reserved) {
                    throw new DomainException(__('La quantité à ramasser dépasse ce qui est disponible pour le client.'));
                }
            }

            $required = $this->amountRequiredFor($quantities);
            $paid = round(array_sum(array_column($payments, 'amount')), 2);

            if ($paid < $required) {
                throw new DomainException(__('Le paiement (:paid $) ne couvre pas le montant exigé (:required $).', [
                    'paid' => number_format($paid, 2),
                    'required' => number_format($required, 2),
                ]));
            }

            if ($paid > $this->balance_due) {
                throw new DomainException(__('Le paiement dépasse le solde de la commande (:balance $).', ['balance' => number_format($this->balance_due, 2)]));
            }

            $pickup = null;

            if ($quantities !== []) {
                $pickup = $this->pickups()->create(['handled_by' => auth()->id()]);

                foreach ($quantities as $lineId => $quantity) {
                    $this->handOver($lines->get($lineId), $quantity, $pickup);
                }
            }

            foreach ($payments as $payment) {
                if ($payment['amount'] <= 0) {
                    continue;
                }

                if (! CustomerPaymentMethod::query()->whereKey($payment['method_id'])->where('is_active', true)->exists()) {
                    throw new DomainException(__('Mode de paiement invalide.'));
                }

                $this->payments()->create([
                    'customer_order_pickup_id' => $pickup?->id,
                    'customer_payment_method_id' => $payment['method_id'],
                    'amount' => round($payment['amount'], 2),
                    'received_by' => auth()->id(),
                ]);
            }

            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $pickup;
        });
    }

    /**
     * Passe la commande à « Ramassée » ou « Livrée » une fois toutes ses lignes facturables remises au client.
     */
    private function refreshStatusFromLines(): void
    {
        $statuses = $this->lines()->get()
            ->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable())
            ->map(fn (CustomerOrderLine $line): CustomerOrderLineStatus => $line->status);

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn (CustomerOrderLineStatus $status): bool => $status === CustomerOrderLineStatus::PickedUp)) {
            $this->update(['status' => CustomerOrderStatus::PickedUp]);
        } elseif ($statuses->every(fn (CustomerOrderLineStatus $status): bool => $status === CustomerOrderLineStatus::Delivered)) {
            $this->update(['status' => CustomerOrderStatus::Delivered]);
        }
    }

    private function handOver(CustomerOrderLine $line, int $quantity, CustomerOrderPickup $pickup): void
    {
        InventoryMovement::record($line->product_id, InventoryStatus::ReservedCustomer, null, $quantity, InventoryMovementType::CustomerPickup, $this);

        InventoryUnit::query()
            ->where('product_id', $line->product_id)
            ->whereNull('delivered_at')
            ->fifo()
            ->limit($quantity)
            ->get()
            ->each(fn (InventoryUnit $unit) => $unit->update(['delivered_at' => today()]));

        $pickedUp = [
            'quantity_reserved' => 0,
            'quantity_on_order' => 0,
            'quantity' => $quantity,
            'status' => CustomerOrderLineStatus::PickedUp,
            'customer_order_pickup_id' => $pickup->id,
            'delivered_at' => now(),
        ];

        if ($quantity === $line->quantity_reserved && $line->quantity_on_order === 0) {
            $line->update($pickedUp);

            return;
        }

        $line->update([
            'quantity_reserved' => $line->quantity_reserved - $quantity,
            'quantity' => $line->quantity - $quantity,
        ]);

        $this->lines()->create([
            ...$pickedUp,
            'product_id' => $line->product_id,
            'unit_price' => $line->unit_price,
            'note' => $line->note,
        ]);
    }

    /**
     * Répartition de la vente entre les vendeurs, dans l'ordre où ils ont été enregistrés.
     *
     * @return array<int, array{user_id: int, percent: int}>
     */
    public function salespeopleShares(): array
    {
        return $this->salespeople()->newPivotQuery()
            ->orderBy('id')
            ->get(['user_id', 'percent'])
            ->map(fn (object $row): array => ['user_id' => (int) $row->user_id, 'percent' => (int) $row->percent])
            ->all();
    }

    /**
     * @param  array<int, array{user_id: int, percent: int}>  $salespeople
     */
    public function syncSalespeople(array $salespeople): void
    {
        $this->salespeople()->sync(collect($salespeople)->mapWithKeys(
            fn (array $row): array => [$row['user_id'] => ['percent' => $row['percent']]],
        )->all());
    }
}
