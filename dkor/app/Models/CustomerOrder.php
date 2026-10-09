<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
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
            $this->refreshStatusFromLines();

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
        $required = $this->requiredPaidTotal(
            fn (CustomerOrderLine $line): float => $line->status->isHandedOver() ? $line->total : ($quantities[$line->id] ?? 0) * $line->unit_price,
        );

        $this->refresh();

        return max(0.0, min(round($required - $this->amount_paid, 2), $this->balance_due));
    }

    /**
     * Montant remboursable pour le retour de cette quantité : la valeur retournée (taxes incluses), sans que le payé
     * restant passe sous ce qu'exige le reste de la commande (100 % des articles remis, dépôt sur les autres).
     */
    public function refundableFor(CustomerOrderLine $line, int $quantity): float
    {
        $returned = round($quantity * $line->unit_price, 2);

        $required = $this->requiredPaidTotal(
            fn (CustomerOrderLine $other): float => $other->status->isHandedOver()
                ? $other->total - ($other->id === $line->id ? $returned : 0)
                : 0,
            excludedValue: $returned,
        );

        $this->refresh();

        return max(0.0, min(self::taxesFor($returned)['total'], round($this->amount_paid - $required, 2)));
    }

    /**
     * Total que le client doit avoir payé : 100 % de la valeur remise (selon $handedOverValue par ligne) et le dépôt
     * minimum sur le reste des lignes facturables, taxes incluses. $excludedValue est retiré du total facturable
     * (articles en cours de retour).
     *
     * @param  callable(CustomerOrderLine): float  $handedOverValue
     */
    private function requiredPaidTotal(callable $handedOverValue, float $excludedValue = 0): float
    {
        $lines = $this->lines()->get()->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable());

        $handedOver = round($lines->sum($handedOverValue), 2);
        $remaining = round($lines->sum(fn (CustomerOrderLine $line): float => $line->total) - $excludedValue - $handedOver, 2);

        return self::taxesFor($handedOver)['total']
            + self::taxesFor($remaining)['total'] * config('sales.deposit_percent') / 100;
    }

    /**
     * Remet au client les quantités choisies (prises dans le stock réservé) et encaisse le paiement exigé.
     * Une ligne remise en partie est séparée : la partie ramassée devient une ligne « Ramassé ». Les unités
     * sortent de l'inventaire (premier entré, premier sorti). Sans article, seul le paiement est encaissé
     * (aucun ramassage n'est créé).
     *
     * @param  array<int, int>  $quantities  quantité à remettre par ligne
     * @param  array<int, array{method_id: int, amount: float, tendered?: float|null}>  $payments  (tendered : comptant reçu)
     * @param  float  $creditUsed  montant payé avec le crédit au compte du client (déduit de ce crédit)
     *
     * @throws DomainException si une quantité n'est pas disponible, si le crédit utilisé dépasse celui du client ou
     *                         si le paiement ne couvre pas le montant exigé.
     */
    public function pickUp(array $quantities, array $payments, float $creditUsed = 0): ?CustomerOrderPickup
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);
        $creditUsed = round($creditUsed, 2);

        if ($creditUsed < 0) {
            throw new DomainException(__('Le crédit utilisé ne peut pas être négatif.'));
        }

        if ($quantities === [] && round(array_sum(array_column($payments, 'amount')) + $creditUsed, 2) <= 0) {
            throw new DomainException(__('Choisissez au moins un article à ramasser ou entrez un paiement.'));
        }

        return DB::transaction(function () use ($quantities, $payments, $creditUsed): ?CustomerOrderPickup {
            $lines = $this->lines()->lockForUpdate()->whereKey(array_keys($quantities))->get()->keyBy('id');

            foreach ($quantities as $lineId => $quantity) {
                $line = $lines->get($lineId);

                if ($line === null || ! $line->status->isPickable() || $quantity > $line->quantity_reserved) {
                    throw new DomainException(__('La quantité à ramasser dépasse ce qui est disponible pour le client.'));
                }
            }

            $customer = customer::query()->whereKey($this->customer_id)->lockForUpdate()->firstOrFail();

            if ($creditUsed > $customer->credit_balance) {
                throw new DomainException(__('Le crédit utilisé dépasse le crédit au compte du client (:credit $).', ['credit' => number_format($customer->credit_balance, 2)]));
            }

            $required = $this->amountRequiredFor($quantities);
            $paid = round(array_sum(array_column($payments, 'amount')) + $creditUsed, 2);

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

            $this->recordPayments($payments, CustomerPaymentType::Payment, $pickup);

            if ($creditUsed > 0) {
                $this->payments()->create([
                    'customer_order_pickup_id' => $pickup?->id,
                    'type' => CustomerPaymentType::CreditUse,
                    'amount' => $creditUsed,
                    'received_by' => auth()->id(),
                ]);

                $customer->decrement('credit_balance', $creditUsed);
            }

            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $pickup;
        });
    }

    /**
     * Reprend des articles remis au client (livrés, ramassés ou expédiés) : le magasin reprend possession de la
     * quantité retournée (elle revient en stock). Pour un échange, la ligne passe à « Retourné » et le montant payé
     * reste sur la commande (crédit pour le produit d'échange). Pour un remboursement, elle passe à « Remboursé » et
     * les montants remboursés sont enregistrés en paiements négatifs. Une ligne retournée en partie est séparée.
     *
     * @param  array<int, array{method_id: int, amount: float}>  $refunds
     *
     * @throws DomainException si la ligne n'a pas été remise au client, si la quantité est invalide ou si le
     *                         remboursement dépasse le montant remboursable.
     */
    public function returnLine(CustomerOrderLine $line, int $quantity, bool $refund, array $refunds = []): void
    {
        DB::transaction(function () use ($line, $quantity, $refund, $refunds): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->status->isHandedOver()) {
                throw new DomainException(__('Seul un article livré, ramassé ou expédié peut être retourné.'));
            }

            if ($quantity < 1 || $quantity > $line->quantity) {
                throw new DomainException(__('La quantité retournée doit être entre 1 et :max.', ['max' => $line->quantity]));
            }

            $refundable = $refund ? $this->refundableFor($line, $quantity) : 0.0;
            $refunded = round(array_sum(array_column($refunds, 'amount')), 2);

            if ($refunded > $refundable) {
                throw new DomainException(__('Le remboursement (:refunded $) dépasse le montant remboursable (:refundable $).', [
                    'refunded' => number_format($refunded, 2),
                    'refundable' => number_format($refundable, 2),
                ]));
            }

            InventoryMovement::record($line->product_id, null, InventoryStatus::InStock, $quantity, InventoryMovementType::CustomerReturn, $this);

            InventoryUnit::query()
                ->where('product_id', $line->product_id)
                ->whereNotNull('delivered_at')
                ->orderByDesc('delivered_at')
                ->orderByDesc('id')
                ->limit($quantity)
                ->get()
                ->each(fn (InventoryUnit $unit) => $unit->update(['delivered_at' => null]));

            $returned = [
                'status' => $refund ? CustomerOrderLineStatus::Refunded : CustomerOrderLineStatus::Returned,
                'returned_at' => now(),
            ];

            if ($quantity === $line->quantity) {
                $line->update($returned);
            } else {
                $line->update(['quantity' => $line->quantity - $quantity]);

                $this->lines()->create([
                    ...$returned,
                    'product_id' => $line->product_id,
                    'customer_order_pickup_id' => $line->customer_order_pickup_id,
                    'quantity' => $quantity,
                    'quantity_reserved' => 0,
                    'quantity_on_order' => 0,
                    'unit_price' => $line->unit_price,
                    'note' => $line->note,
                    'delivered_at' => $line->delivered_at,
                ]);
            }

            $this->recordPayments($refunds, CustomerPaymentType::Refund);

            $this->recalculateBalance();
            $this->refreshStatusFromLines();
        });
    }

    /**
     * Crédit de la commande : ce que le client a payé en trop (après un échange, par exemple).
     */
    public function credit(): float
    {
        return max(0.0, round(-$this->balance_due, 2));
    }

    /**
     * Rembourse tout ou partie du crédit de la commande, sur un ou plusieurs modes de paiement.
     *
     * @param  array<int, array{method_id: int, amount: float}>  $refunds
     *
     * @throws DomainException si le remboursement est nul ou dépasse le crédit.
     */
    public function refundCredit(array $refunds): void
    {
        DB::transaction(function () use ($refunds): void {
            $this->recalculateBalance();
            $refunded = round(array_sum(array_column($refunds, 'amount')), 2);

            if ($refunded <= 0 || $refunded > $this->credit()) {
                throw new DomainException(__('Le remboursement doit être entre 0,01 $ et le crédit de la commande (:credit $).', ['credit' => number_format($this->credit(), 2)]));
            }

            $this->recordPayments($refunds, CustomerPaymentType::Refund);
            $this->recalculateBalance();
        });
    }

    /**
     * Porte le crédit de la commande au compte du client (champ `credit_balance`) pour une utilisation future; la
     * commande est soldée.
     *
     * @throws DomainException si la commande n'a pas de crédit.
     */
    public function transferCreditToCustomer(): float
    {
        return DB::transaction(function (): float {
            $this->recalculateBalance();
            $credit = $this->credit();

            if ($credit <= 0) {
                throw new DomainException(__('Cette commande n\'a pas de crédit à porter au compte du client.'));
            }

            $this->payments()->create([
                'type' => CustomerPaymentType::CreditTransfer,
                'amount' => -$credit,
                'received_by' => auth()->id(),
            ]);

            customer::query()->whereKey($this->customer_id)->lockForUpdate()->firstOrFail()->increment('credit_balance', $credit);

            $this->recalculateBalance();

            return $credit;
        });
    }

    /**
     * Enregistre des montants reçus (paiement) ou rendus (remboursement, en négatif), un par mode de paiement. En
     * comptant, le montant perçu ou remis est arrondi au 5 ¢ près (l'écart est conservé) et, pour un paiement, le
     * montant reçu du client (au moins le montant arrondi) et la monnaie rendue sont enregistrés.
     *
     * @param  array<int, array{method_id: int, amount: float, tendered?: float|null}>  $payments
     *
     * @throws DomainException si un mode est invalide ou si le comptant reçu ne couvre pas le montant arrondi.
     */
    private function recordPayments(array $payments, CustomerPaymentType $type, ?CustomerOrderPickup $pickup = null): void
    {
        foreach ($payments as $payment) {
            if ($payment['amount'] <= 0) {
                continue;
            }

            $method = CustomerPaymentMethod::query()->whereKey($payment['method_id'])->where('is_active', true)->first();

            if ($method === null) {
                throw new DomainException(__('Mode de paiement invalide.'));
            }

            $amount = round($payment['amount'], 2);
            $cash = [];

            if ($method->isCash()) {
                $rounded = CustomerPaymentMethod::roundCash($amount);
                $cash = ['rounding_adjustment' => round($rounded - $amount, 2)];

                if ($type === CustomerPaymentType::Payment) {
                    $tendered = round($payment['tendered'] ?? $rounded, 2);

                    if ($tendered < $rounded) {
                        throw new DomainException(__('Le comptant reçu (:tendered $) ne couvre pas le montant à percevoir (:rounded $).', [
                            'tendered' => number_format($tendered, 2),
                            'rounded' => number_format($rounded, 2),
                        ]));
                    }

                    $cash += ['cash_tendered' => $tendered, 'change_given' => round($tendered - $rounded, 2)];
                }
            }

            $this->payments()->create([
                'customer_order_pickup_id' => $pickup?->id,
                'customer_payment_method_id' => $method->id,
                'type' => $type,
                'amount' => $type === CustomerPaymentType::Payment ? $amount : -$amount,
                'received_by' => auth()->id(),
                ...$cash,
            ]);
        }
    }

    /**
     * Passe la commande à « Ramassée » ou « Livrée » une fois toutes ses lignes facturables remises au client; une
     * commande qui l'était et qui reçoit un nouvel article (ex. un échange) revient « En attente ».
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
        } elseif (in_array($this->status, [CustomerOrderStatus::PickedUp, CustomerOrderStatus::Delivered], true)) {
            $this->update(['status' => CustomerOrderStatus::Pending]);
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
