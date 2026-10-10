<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\DefectiveResolution;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierOrderLineStatus;
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
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $store_id
 * @property CustomerOrderStatus $status
 * @property float $subtotal
 * @property float $total
 * @property float $amount_paid
 * @property float $balance_due
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read customer $customer
 * @property-read Store|null $store
 * @property-read User|null $creator
 * @property-read Collection<int, User> $salespeople
 * @property-read Collection<int, CustomerOrderLine> $lines
 * @property-read Collection<int, CustomerOrderPickup> $pickups
 * @property-read Collection<int, CustomerOrderPayment> $payments
 * @property-read Collection<int, CustomerOrderTax> $taxLines
 */
#[Fillable(['customer_id', 'store_id', 'status', 'subtotal', 'total', 'amount_paid', 'balance_due', 'created_by'])]
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
        'total' => 'float',
        'amount_paid' => 'float',
    ];

    protected static function booted(): void
    {
        static::created(fn (CustomerOrder $order) => $order->freezeTaxes());
    }

    /** @return BelongsTo<customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(customer::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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

    /**
     * Taxes figées de la commande, les individuelles avant celles en cascade.
     *
     * @return HasMany<CustomerOrderTax, $this>
     */
    public function taxLines(): HasMany
    {
        return $this->hasMany(CustomerOrderTax::class)->orderBy('is_compound')->orderBy('id');
    }

    /**
     * Copie sur la commande les taxes en vigueur à sa date de création dans la province de son magasin, avec les
     * numéros de taxe du marchand. Appelée une seule fois, à la création : les taux ne changent plus ensuite.
     *
     * @throws DomainException si la commande n'a pas de magasin ou si aucune taxe n'est en vigueur pour sa province.
     */
    public function freezeTaxes(): void
    {
        $store = $this->store;

        if ($store === null) {
            throw new DomainException(__('Votre utilisateur n\'a pas de magasin : une commande doit appartenir à un magasin pour connaître ses taxes.'));
        }

        $taxes = Tax::query()
            ->activeOn($this->created_at ?? Carbon::today())
            ->where('province', $store->province)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        if ($taxes->isEmpty()) {
            throw new DomainException(__('Aucune taxe n\'est en vigueur pour la province du magasin « :store » (:province). Ajoutez-les dans Comptabilité > Taxes.', [
                'store' => $store->name,
                'province' => $store->province->label(),
            ]));
        }

        $registrations = $store->taxRegistrations()->pluck('number', 'tax_name');

        foreach ($taxes as $tax) {
            $this->taxLines()->create([
                'tax_id' => $tax->id,
                'name' => $tax->name,
                'rate' => $tax->rate,
                'is_compound' => $tax->is_compound,
                'registration_number' => $registrations[$tax->name] ?? null,
            ]);
        }

        $this->unsetRelation('taxLines');
    }

    /** @return HasMany<CustomerOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class);
    }

    /**
     * Ajoute le produit à la commande : la quantité est d'abord réservée dans le stock disponible, le reste
     * est mis en commande sur le brouillon de commande fournisseur. La ligne modifiable existante du produit est
     * augmentée, sinon une ligne est créée au prix vendant. Avec un prix imposé (ex. remplacement d'un défectueux),
     * une ligne distincte est toujours créée à ce prix.
     *
     * @throws DomainException si une quantité doit être commandée et que le produit ne peut pas l'être.
     */
    public function addProduct(Product $product, int $quantity = 1, ?float $unitPrice = null, ?string $note = null): CustomerOrderLine
    {
        if ($product->is_custom) {
            throw new DomainException(__('Produit sur mesure : saisissez ses spécifications, son coût soumis et son prix.'));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        return DB::transaction(function () use ($product, $quantity, $unitPrice, $note): CustomerOrderLine {
            $line = $unitPrice !== null
                ? $this->lines()->make(['product_id' => $product->id, 'unit_price' => round($unitPrice, 2), 'is_taxable' => $product->is_taxable, 'note' => $note])
                : $this->lines()
                    ->where('product_id', $product->id)
                    ->whereIn('status', [CustomerOrderLineStatus::InStock, CustomerOrderLineStatus::OnOrder])
                    ->lockForUpdate()
                    ->first()
                    ?? $this->lines()->make(['product_id' => $product->id, 'unit_price' => $product->selling_price, 'is_taxable' => $product->is_taxable]);

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
     * Ajoute une pièce de remplacement : elle est toujours commandée (jamais prise en stock) sur le brouillon de
     * commande du fournisseur de la pièce, sans frais pour le client en attendant le prix de vente des pièces. La
     * ligne en commande existante de la pièce est augmentée, sinon une ligne est créée.
     *
     * @throws DomainException si la quantité est invalide ou si le fournisseur ne prend pas de commande.
     */
    public function addPart(Part $part, int $quantity = 1, ?string $note = null): CustomerOrderLine
    {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        return DB::transaction(function () use ($part, $quantity, $note): CustomerOrderLine {
            $line = $this->lines()
                ->where('part_id', $part->id)
                ->where('status', CustomerOrderLineStatus::OnOrder)
                ->lockForUpdate()
                ->first()
                ?? $this->lines()->make(['part_id' => $part->id, 'unit_price' => 0, 'note' => $note]);

            $this->fillLineQuantities($line, 0, $line->quantity_on_order + $quantity);
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

            if ($line->is_custom && $reserved > $line->quantity_reserved) {
                throw new DomainException(__('Un article sur mesure se commande toujours : il ne se prend pas en stock.'));
            }

            if ($line->isPart() && $reserved !== $line->quantity_reserved) {
                throw new DomainException(__('Une pièce se commande toujours : elle ne se prend pas en stock.'));
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

            if (! $line->isPart()) {
                $this->moveReservation($line->product_id, InventoryStatus::InStock, InventoryStatus::ReservedCustomer, $quantity);
            }

            $onOrder = $line->quantity_on_order - $quantity;

            $line->update([
                'quantity_reserved' => $line->quantity_reserved + $quantity,
                'quantity_on_order' => $onOrder,
                'status' => match (true) {
                    $onOrder === 0 => CustomerOrderLineStatus::Received,
                    $line->status === CustomerOrderLineStatus::CancellationRequested => CustomerOrderLineStatus::CancellationRequested,
                    default => CustomerOrderLineStatus::Ordered,
                },
            ]);
        });
    }

    /**
     * Répercute des articles reçus endommagés : ils ne sont pas remis au client, la quantité est donc recommandée
     * sur le brouillon du fournisseur (ligne « En commande » distincte si une partie de la ligne est déjà en main).
     */
    public function applySupplierDamagedReceipt(CustomerOrderLine $line, int $quantity): void
    {
        DB::transaction(function () use ($line, $quantity): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);
            $quantity = min($quantity, $line->quantity_on_order);

            if ($quantity < 1) {
                return;
            }

            if ($quantity === $line->quantity_on_order && $line->quantity_reserved === 0) {
                $line->update(['supplier_order_line_id' => null, 'status' => CustomerOrderLineStatus::OnOrder]);
                $this->syncSupplierLine($line);

                return;
            }

            $onOrder = $line->quantity_on_order - $quantity;

            $line->update([
                'quantity_on_order' => $onOrder,
                'quantity' => $line->quantity - $quantity,
                'status' => $onOrder === 0 ? CustomerOrderLineStatus::Received : $line->status,
            ]);

            $reorder = $this->lines()->create([
                ...$line->saleAttributes(),
                'quantity' => $quantity,
                'quantity_reserved' => 0,
                'quantity_on_order' => $quantity,
                'status' => CustomerOrderLineStatus::OnOrder,
            ]);

            $this->syncSupplierLine($reorder);
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

            if (! $line->isPart()) {
                $this->moveReservation($line->product_id, InventoryStatus::ReservedCustomer, InventoryStatus::InStock, $quantity);
            }

            $line->update([
                'quantity_reserved' => $line->quantity_reserved - $quantity,
                'quantity_on_order' => $line->quantity_on_order + $quantity,
                'status' => CustomerOrderLineStatus::Ordered,
            ]);
        });
    }

    /**
     * Répercute une demande d'annulation faite au fournisseur : la ligne passe « Demande d'annulation » jusqu'à sa
     * réponse (confirmée : voir applySupplierCancellation; refusée : applySupplierCancellationRejected).
     */
    public function applySupplierCancellationRequest(CustomerOrderLine $line): void
    {
        $this->lines()->whereKey($line->id)
            ->where('status', CustomerOrderLineStatus::Ordered)
            ->update(['status' => CustomerOrderLineStatus::CancellationRequested]);
    }

    /**
     * Le fournisseur refuse l'annulation : la ligne redevient « Commandé ».
     */
    public function applySupplierCancellationRejected(CustomerOrderLine $line): void
    {
        $this->lines()->whereKey($line->id)
            ->where('status', CustomerOrderLineStatus::CancellationRequested)
            ->update(['status' => CustomerOrderLineStatus::Ordered]);
    }

    /**
     * Frais d'annulation de cette quantité d'une ligne, selon le taux du magasin de la commande.
     */
    public function cancellationFeeFor(CustomerOrderLine $line, int $quantity): float
    {
        return round($quantity * $line->unit_price * ($this->store->cancellation_fee_percent ?? 0) / 100, 2);
    }

    /**
     * Le client annule, sans attendre la réponse du fournisseur, ce qui reste à recevoir d'une ligne commandée : la
     * partie en commande devient une ligne « Annulé » qui porte les frais d'annulation du magasin, et elle n'est
     * plus liée à la commande fournisseur (ce qui arrivera entre en stock disponible). Une demande d'annulation est
     * envoyée au fournisseur si elle ne l'a pas déjà été. La partie déjà en stock ou reçue reste au client.
     *
     * @return float Frais d'annulation facturés.
     *
     * @throws DomainException si la ligne n'est pas commandée ou en demande d'annulation.
     */
    public function cancelLineWithFee(CustomerOrderLine $line): float
    {
        $supplierLine = null;

        $fee = DB::transaction(function () use ($line, &$supplierLine): float {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! in_array($line->status, [CustomerOrderLineStatus::Ordered, CustomerOrderLineStatus::CancellationRequested], true) || $line->quantity_on_order < 1) {
                throw new DomainException(__('Seule une ligne commandée ou en demande d\'annulation peut être annulée avec frais.'));
            }

            $supplierLine = $line->supplierOrderLine()->with('order')->first();
            $fee = $this->cancellationFeeFor($line, $line->quantity_on_order);

            $cancelled = [
                'status' => CustomerOrderLineStatus::Cancelled,
                'supplier_order_line_id' => null,
                'cancellation_fee' => $fee,
            ];

            if ($line->quantity_reserved === 0) {
                $line->update($cancelled);
            } else {
                $this->lines()->create([
                    ...$line->saleAttributes(),
                    ...$cancelled,
                    'quantity' => $line->quantity_on_order,
                    'quantity_reserved' => 0,
                    'quantity_on_order' => $line->quantity_on_order,
                ]);

                $line->update([
                    'quantity_on_order' => 0,
                    'quantity' => $line->quantity_reserved,
                    'status' => $supplierLine !== null && $supplierLine->quantity_received > 0
                        ? CustomerOrderLineStatus::Received
                        : CustomerOrderLineStatus::InStock,
                ]);
            }

            $this->recalculateBalance();

            return $fee;
        });

        if ($supplierLine !== null && $supplierLine->status === SupplierOrderLineStatus::Active && $supplierLine->quantity_outstanding > 0 && $supplierLine->order->status->isOpen()) {
            $supplierLine->requestCancellation(__('Annulation demandée par le client (commande client #:id).', ['id' => $this->id]));
        }

        return $fee;
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
                'is_taxable' => Product::query()->whereKey($replacement->product_id)->value('is_taxable') ?? true,
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
            $unitCost = $line->is_custom ? (float) $line->unit_cost : $supplierLine->unit_cost;

            if ($supplierLine->quantity !== $line->quantity_on_order || round($unitCost, 2) !== round($supplierLine->unit_cost, 2)) {
                $this->guardSupplierLineEditable($supplierLine);
                $supplierLine->order->updateLine($supplierLine, $line->quantity_on_order, $unitCost);
            }

            if ($line->is_custom && $supplierLine->description !== $this->customSpecifications($line)) {
                $this->guardSupplierLineEditable($supplierLine);
                $supplierLine->update(['description' => $this->customSpecifications($line)]);
            }

            return;
        }

        if ($line->isService()) {
            $this->orderServiceFromSupplier($line);

            return;
        }

        if ($line->isPart()) {
            $this->orderPartFromSupplier($line);

            return;
        }

        $product = $line->product()->with('supplier')->firstOrFail();

        if ($product->is_non_orderable || ! $product->supplier->is_active || ! $product->supplier->orderable || $product->supplier->type !== SupplierType::Product) {
            throw new DomainException(__('Le produit :model ne peut pas être commandé chez :supplier : il faut le prendre en stock.', [
                'model' => $product->model,
                'supplier' => $product->supplier->name,
            ]));
        }

        $supplierLine = $this->supplierDraftFor($product->supplier_id, SupplierType::Product)->addLine([
            'product_id' => $product->id,
            'description' => $line->is_custom ? $this->customSpecifications($line) : null,
            'quantity' => $line->quantity_on_order,
            'unit_cost' => $line->is_custom ? (float) $line->unit_cost : $product->cost,
        ]);

        $line->update(['supplier_order_line_id' => $supplierLine->id]);
    }

    /**
     * Brouillon de commande du fournisseur, du type voulu (produits ou services), créé au besoin.
     */
    private function supplierDraftFor(int $supplierId, SupplierType $type): SupplierOrder
    {
        return SupplierOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('type', $type)
            ->where('status', SupplierOrderStatus::Draft)
            ->lockForUpdate()
            ->first()
            ?? SupplierOrder::create([
                'type' => $type,
                'supplier_id' => $supplierId,
                'created_by' => auth()->id(),
            ]);
    }

    /**
     * Ajoute un service externe au brouillon de commande de services de son fournisseur (description de la vente,
     * coût du fournisseur pour ce service).
     *
     * @throws DomainException si le fournisseur ne prend pas de commande.
     */
    private function orderServiceFromSupplier(CustomerOrderLine $line): void
    {
        $supplier = $line->supplier()->firstOrFail();

        if (! $supplier->is_active || ! $supplier->orderable) {
            throw new DomainException(__('Aucune commande ne peut être passée chez :supplier.', ['supplier' => $supplier->name]));
        }

        $service = $line->service()->firstOrFail();

        $supplierLine = $this->supplierDraftFor($supplier->id, SupplierType::Service)->addLine([
            'description' => Str::limit($service->name.' — '.$line->description.' (commande client #'.$this->id.')', 255, ''),
            'quantity' => $line->quantity_on_order,
            'unit_cost' => $service->pricingFor($supplier->id)['cost'],
        ]);

        $line->update(['supplier_order_line_id' => $supplierLine->id]);
    }

    /**
     * Ajoute une pièce au brouillon de commande de produits de son fournisseur, à son dernier coût. La description
     * identifie la pièce et le client pour la retrouver à la réception.
     *
     * @throws DomainException si le fournisseur ne prend pas de commande.
     */
    private function orderPartFromSupplier(CustomerOrderLine $line): void
    {
        $part = $line->part()->with('supplier')->firstOrFail();
        $supplier = $part->supplier;

        if (! $supplier->is_active || ! $supplier->orderable || $supplier->type !== SupplierType::Product) {
            throw new DomainException(__('Aucune commande ne peut être passée chez :supplier.', ['supplier' => $supplier->name]));
        }

        $supplierLine = $this->supplierDraftFor($supplier->id, SupplierType::Product)->addLine([
            'part_id' => $part->id,
            'description' => Str::limit(__('Pièce :model — :description (commande client #:id)', ['model' => $part->model, 'description' => $part->description, 'id' => $this->id]), 255, ''),
            'quantity' => $line->quantity_on_order,
            'unit_cost' => $part->last_cost,
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
     * Recalcule les totaux : sous-total des lignes facturables et des frais d'annulation, taxes (sur la partie
     * taxable seulement : lignes taxables et frais), total, montant payé et solde à payer.
     */
    public function recalculateBalance(): void
    {
        $lines = $this->lines()->get();
        $billable = $lines->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable());
        $taxable = round($billable->filter(fn (CustomerOrderLine $line): bool => $line->is_taxable)->sum(fn (CustomerOrderLine $line): float => $line->total)
            + $lines->sum(fn (CustomerOrderLine $line): float => (float) $line->cancellation_fee), 2);
        $exempt = round($billable->reject(fn (CustomerOrderLine $line): bool => $line->is_taxable)->sum(fn (CustomerOrderLine $line): float => $line->total), 2);
        $taxes = $this->taxesFor($taxable);
        $total = round($taxes['total'] + $exempt, 2);
        $amountPaid = round((float) $this->payments()->sum('amount'), 2);

        foreach ($this->taxLines as $taxLine) {
            $taxLine->update(['amount' => $taxes['amounts'][$taxLine->id]]);
        }

        $this->update([
            'subtotal' => round($taxable + $exempt, 2),
            'total' => $total,
            'amount_paid' => $amountPaid,
            'balance_due' => round($total - $amountPaid, 2),
        ]);
    }

    /**
     * Taxes de la commande sur un montant avant taxes, arrondies au cent chacune. Les taxes individuelles se
     * calculent sur le montant; celles en cascade sur le montant plus les taxes individuelles.
     *
     * @return array{amounts: array<int, float>, total: float} montant par taxe (clé : id de la ligne de taxe) et montant taxes incluses
     */
    public function taxesFor(float $amount): array
    {
        $amounts = [];
        $individual = 0.0;

        foreach ($this->taxLines->sortBy('is_compound') as $taxLine) {
            $base = $taxLine->is_compound ? $amount + $individual : $amount;
            $amounts[$taxLine->id] = round($base * $taxLine->rate / 100, 2);

            if (! $taxLine->is_compound) {
                $individual += $amounts[$taxLine->id];
            }
        }

        return ['amounts' => $amounts, 'total' => round($amount + array_sum($amounts), 2)];
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
            excludedLine: $line,
            excludedValue: $returned,
        );

        $this->refresh();

        $returnedTotal = $line->is_taxable ? $this->taxedTotal($returned, 0) : $returned;

        return max(0.0, min($returnedTotal, round($this->amount_paid - $required, 2)));
    }

    /**
     * Total que le client doit avoir payé : 100 % de la valeur remise (selon $handedOverValue par ligne) et le dépôt
     * minimum sur le reste des lignes facturables (celui du magasin pour le sur-mesure), taxes incluses sur les lignes
     * taxables. $excludedValue (de la ligne $excludedLine) est retiré du total facturable (articles en cours de retour).
     *
     * @param  callable(CustomerOrderLine): float  $handedOverValue
     */
    private function requiredPaidTotal(callable $handedOverValue, ?CustomerOrderLine $excludedLine = null, float $excludedValue = 0): float
    {
        $lines = $this->lines()->get()->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable());

        $handedOver = ['taxable' => 0.0, 'exempt' => 0.0];
        $remaining = [];

        foreach ($lines as $line) {
            $tax = $line->is_taxable ? 'taxable' : 'exempt';
            $handed = $handedOverValue($line);
            $excluded = $excludedLine !== null && $line->id === $excludedLine->id ? $excludedValue : 0.0;

            $handedOver[$tax] += $handed;
            $remaining[$line->is_custom ? 'custom' : 'regular'][$tax] = ($remaining[$line->is_custom ? 'custom' : 'regular'][$tax] ?? 0.0)
                + $line->total - $excluded - $handed;
        }

        $deposits = [
            'regular' => config('sales.deposit_percent'),
            'custom' => $this->store->custom_deposit_percent ?? config('sales.deposit_percent'),
        ];

        $required = $this->taxedTotal(round($handedOver['taxable'], 2), round($handedOver['exempt'], 2));

        foreach ($remaining as $kind => $amounts) {
            $required += $this->taxedTotal(round($amounts['taxable'] ?? 0.0, 2), round($amounts['exempt'] ?? 0.0, 2)) * $deposits[$kind] / 100;
        }

        return $required;
    }

    /**
     * Montant taxes incluses d'une partie taxable et d'une partie non taxable.
     */
    private function taxedTotal(float $taxable, float $exempt): float
    {
        return round($this->taxesFor($taxable)['total'] + $exempt, 2);
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

            if ($line->isService() || ! $line->status->isHandedOver()) {
                throw new DomainException(__('Seul un article livré, ramassé ou expédié peut être retourné.'));
            }

            if ($line->isPart()) {
                throw new DomainException(__('Une pièce de remplacement ne se retourne pas en magasin.'));
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
                    ...$line->saleAttributes(),
                    ...$returned,
                    'customer_order_pickup_id' => $line->customer_order_pickup_id,
                    'quantity' => $quantity,
                    'quantity_reserved' => 0,
                    'quantity_on_order' => 0,
                    'delivered_at' => $line->delivered_at,
                ]);
            }

            $this->recordPayments($refunds, CustomerPaymentType::Refund);

            $this->recalculateBalance();
            $this->refreshStatusFromLines();
        });
    }

    /**
     * Vend un article sur mesure à partir d'un produit gabarit : une ligne distincte, toujours commandée (jamais prise
     * en stock), avec ses spécifications, le coût soumis par le fournisseur et le prix saisi. La ligne de commande
     * fournisseur reçoit les spécifications et le coût soumis.
     *
     * @throws DomainException si le produit n'est pas un gabarit sur mesure ou si une information manque.
     */
    public function addCustomProduct(Product $product, int $quantity, string $specifications, float $unitCost, float $unitPrice, ?string $quoteNumber = null): CustomerOrderLine
    {
        if (! $product->is_custom) {
            throw new DomainException(__('Ce produit n\'est pas un produit sur mesure.'));
        }

        $this->guardCustomSale($quantity, $specifications, $unitCost, $unitPrice);

        return DB::transaction(function () use ($product, $quantity, $specifications, $unitCost, $unitPrice, $quoteNumber): CustomerOrderLine {
            $line = $this->lines()->create([
                'product_id' => $product->id,
                'description' => Str::limit(trim($specifications), 255, ''),
                'quantity' => $quantity,
                'quantity_reserved' => 0,
                'quantity_on_order' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'unit_cost' => round($unitCost, 2),
                'quote_number' => filled($quoteNumber) ? trim($quoteNumber) : null,
                'is_taxable' => $product->is_taxable,
                'is_custom' => true,
                'status' => CustomerOrderLineStatus::OnOrder,
            ]);

            $this->syncSupplierLine($line);
            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $line;
        });
    }

    /**
     * Modifie un article sur mesure tant qu'il n'est pas commandé au fournisseur (la ligne fournisseur suit :
     * quantité, coût soumis, spécifications); la note se modifie en tout temps.
     *
     * @throws DomainException si l'article est déjà commandé ou si une information manque.
     */
    public function updateCustomLine(CustomerOrderLine $line, int $quantity, string $specifications, float $unitCost, float $unitPrice, ?string $quoteNumber, ?string $note): void
    {
        DB::transaction(function () use ($line, $quantity, $specifications, $unitCost, $unitPrice, $quoteNumber, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->is_custom) {
                throw new DomainException(__('Cette ligne n\'est pas un article sur mesure.'));
            }

            $quoteNumber = filled($quoteNumber) ? trim($quoteNumber) : null;
            $changesSale = $quantity !== $line->quantity
                || trim($specifications) !== (string) $line->description
                || round($unitCost, 2) !== round((float) $line->unit_cost, 2)
                || round($unitPrice, 2) !== round($line->unit_price, 2)
                || $quoteNumber !== $line->quote_number;

            if ($changesSale) {
                if ($line->status !== CustomerOrderLineStatus::OnOrder || $line->quantity_reserved > 0) {
                    throw new DomainException(__('Cet article sur mesure est déjà commandé : seule la note peut changer.'));
                }

                $this->guardCustomSale($quantity, $specifications, $unitCost, $unitPrice);

                $line->update([
                    'quantity' => $quantity,
                    'quantity_on_order' => $quantity,
                    'description' => Str::limit(trim($specifications), 255, ''),
                    'unit_cost' => round($unitCost, 2),
                    'unit_price' => round($unitPrice, 2),
                    'quote_number' => $quoteNumber,
                ]);

                $this->syncSupplierLine($line);
            }

            $line->update(['note' => filled($note) ? $note : null]);

            $this->recalculateBalance();
        });
    }

    private function guardCustomSale(int $quantity, string $specifications, float $unitCost, float $unitPrice): void
    {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        if (blank($specifications)) {
            throw new DomainException(__('Indiquez les spécifications de l\'article sur mesure.'));
        }

        if ($unitCost < 0 || $unitPrice < 0) {
            throw new DomainException(__('Le coût soumis et le prix vendant ne peuvent pas être négatifs.'));
        }
    }

    /**
     * Description transmise au fournisseur pour un article sur mesure : spécifications et numéro de soumission.
     */
    private function customSpecifications(CustomerOrderLine $line): string
    {
        return Str::limit(trim((string) $line->description).(filled($line->quote_number) ? ' (soumission '.$line->quote_number.')' : ''), 255, '');
    }

    /**
     * Vend un service : interne (« À faire », rendu par le magasin) ou externe (« En commande », ajouté au brouillon
     * de commande de services du fournisseur choisi). Le prix et la taxabilité viennent du service et du fournisseur
     * si le prix n'est pas imposé.
     *
     * @throws DomainException si le service est inactif, si le fournisseur ne l'offre pas ou si la description manque.
     */
    public function addService(Service $service, ?int $supplierId, int $quantity, string $description, ?float $unitPrice = null): CustomerOrderLine
    {
        if (! $service->is_active) {
            throw new DomainException(__('Ce service n\'est plus offert.'));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        if (blank($description)) {
            throw new DomainException(__('Décrivez le service rendu.'));
        }

        if ($service->is_internal) {
            $supplierId = null;
        } elseif ($supplierId === null || ! $service->suppliers()->whereKey($supplierId)->exists()) {
            throw new DomainException(__('Choisissez un fournisseur qui offre ce service.'));
        }

        return DB::transaction(function () use ($service, $supplierId, $quantity, $description, $unitPrice): CustomerOrderLine {
            $line = $this->lines()->create([
                'service_id' => $service->id,
                'supplier_id' => $supplierId,
                'description' => Str::limit(trim($description), 255, ''),
                'quantity' => $quantity,
                'quantity_reserved' => 0,
                'quantity_on_order' => $supplierId === null ? 0 : $quantity,
                'unit_price' => round($unitPrice ?? $service->pricingFor($supplierId)['selling_price'], 2),
                'is_taxable' => $service->is_taxable,
                'status' => $supplierId === null ? CustomerOrderLineStatus::ToDo : CustomerOrderLineStatus::OnOrder,
            ]);

            if ($supplierId !== null) {
                $this->syncSupplierLine($line);
            }

            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $line;
        });
    }

    /**
     * Modifie une ligne de service : quantité, prix et description tant qu'elle n'est pas commandée au fournisseur
     * (la ligne de commande fournisseur suit), note en tout temps.
     *
     * @throws DomainException si la ligne n'est plus modifiable.
     */
    public function updateServiceLine(CustomerOrderLine $line, int $quantity, float $unitPrice, string $description, ?string $note): void
    {
        DB::transaction(function () use ($line, $quantity, $unitPrice, $description, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->isService()) {
                throw new DomainException(__('Cette ligne n\'est pas un service.'));
            }

            $changesSale = $quantity !== $line->quantity
                || round($unitPrice, 2) !== round($line->unit_price, 2)
                || trim($description) !== (string) $line->description;

            if ($changesSale) {
                if (! $line->status->isEditable()) {
                    throw new DomainException(__('Ce service est déjà commandé ou complété : seule la note peut changer.'));
                }

                if ($quantity < 1 || blank($description)) {
                    throw new DomainException(__('Indiquez une quantité d\'au moins 1 et une description.'));
                }

                $line->update([
                    'quantity' => $quantity,
                    'quantity_on_order' => $line->supplier_id === null ? 0 : $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'description' => Str::limit(trim($description), 255, ''),
                ]);

                if ($line->supplier_id !== null) {
                    $this->syncSupplierLine($line);
                }
            }

            $line->update(['note' => filled($note) ? $note : null]);

            $this->recalculateBalance();
        });
    }

    /**
     * Le service est rendu : la ligne passe à « Complété » (payable à 100 %).
     *
     * @throws DomainException si la ligne n'est pas un service à faire ou commandé.
     */
    public function completeServiceLine(CustomerOrderLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->isService() || ! in_array($line->status, [CustomerOrderLineStatus::ToDo, CustomerOrderLineStatus::Ordered], true)) {
                throw new DomainException(__('Seul un service à faire ou commandé peut être complété.'));
            }

            $line->update([
                'status' => CustomerOrderLineStatus::Completed,
                'quantity_on_order' => 0,
                'delivered_at' => now(),
            ]);

            $this->recalculateBalance();
            $this->refreshStatusFromLines();
        });
    }

    /**
     * Produit défectueux qui reste chez le client : une pièce de remplacement (texte libre) est commandée sans frais
     * sur le brouillon du fournisseur du produit, et un dossier défectueux est ouvert.
     *
     * @throws DomainException si l'article n'a pas été remis au client, si la quantité est invalide ou si le
     *                         fournisseur ne prend pas de commande.
     */
    public function orderReplacementPart(CustomerOrderLine $line, int $quantity, string $part, ?string $reason = null): DefectiveProduct
    {
        return DB::transaction(function () use ($line, $quantity, $part, $reason): DefectiveProduct {
            $line = $this->lines()->with('product.supplier')->lockForUpdate()->findOrFail($line->id);
            $this->guardDefectiveQuantity($line, $quantity);

            if (blank($part)) {
                throw new DomainException(__('Indiquez la pièce de remplacement à commander.'));
            }

            $supplier = $line->product->supplier;

            if (! $supplier->is_active || ! $supplier->orderable || $supplier->type !== SupplierType::Product) {
                throw new DomainException(__('Aucune commande ne peut être passée chez :supplier.', ['supplier' => $supplier->name]));
            }

            $supplierLine = $this->supplierDraftFor($line->product->supplier_id, SupplierType::Product)->addLine([
                'description' => Str::limit(__('Pièce : :part — :model (commande client #:id)', ['part' => trim($part), 'model' => $line->product->model, 'id' => $this->id]), 255, ''),
                'quantity' => $quantity,
                'unit_cost' => 0,
            ]);

            return DefectiveProduct::create([
                'product_id' => $line->product_id,
                'customer_order_id' => $this->id,
                'customer_order_line_id' => $line->id,
                'quantity' => $quantity,
                'resolution' => DefectiveResolution::PartOrder,
                'reason' => filled($reason) ? $reason : null,
                'replacement_part' => trim($part),
                'supplier_order_line_id' => $supplierLine->id,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Reprend un produit défectueux : il passe en inventaire défectueux et un dossier défectueux est ouvert (raison).
     * Le client reçoit soit un produit de remplacement (nouvelle ligne au même prix, prise en stock ou commandée),
     * soit un remboursement sans frais.
     *
     * @param  array<int, array{method_id: int, amount: float}>  $refunds
     *
     * @throws DomainException si l'article n'a pas été remis, si la raison manque ou si le remboursement dépasse le
     *                         montant remboursable.
     */
    public function returnDefective(CustomerOrderLine $line, int $quantity, string $reason, bool $replace, array $refunds = []): DefectiveProduct
    {
        if (blank($reason)) {
            throw new DomainException(__('Indiquez la raison du défaut.'));
        }

        return DB::transaction(function () use ($line, $quantity, $reason, $replace, $refunds): DefectiveProduct {
            $line = $this->lines()->with('product')->lockForUpdate()->findOrFail($line->id);
            $this->guardDefectiveQuantity($line, $quantity);

            $refundable = $replace ? 0.0 : $this->refundableFor($line, $quantity);
            $refunded = round(array_sum(array_column($refunds, 'amount')), 2);

            if ($refunded > $refundable) {
                throw new DomainException(__('Le remboursement (:refunded $) dépasse le montant remboursable (:refundable $).', [
                    'refunded' => number_format($refunded, 2),
                    'refundable' => number_format($refundable, 2),
                ]));
            }

            InventoryMovement::record($line->product_id, null, InventoryStatus::DefectiveStock, $quantity, InventoryMovementType::CustomerDefectiveReturn, $this);

            $returned = [
                'status' => $replace ? CustomerOrderLineStatus::Returned : CustomerOrderLineStatus::Refunded,
                'returned_at' => now(),
            ];

            if ($quantity === $line->quantity) {
                $line->update($returned);
                $returnedLine = $line;
            } else {
                $line->update(['quantity' => $line->quantity - $quantity]);

                $returnedLine = $this->lines()->create([
                    ...$line->saleAttributes(),
                    ...$returned,
                    'customer_order_pickup_id' => $line->customer_order_pickup_id,
                    'quantity' => $quantity,
                    'quantity_reserved' => 0,
                    'quantity_on_order' => 0,
                    'delivered_at' => $line->delivered_at,
                ]);
            }

            $defective = DefectiveProduct::create([
                'product_id' => $line->product_id,
                'customer_order_id' => $this->id,
                'customer_order_line_id' => $returnedLine->id,
                'quantity' => $quantity,
                'resolution' => $replace ? DefectiveResolution::Replacement : DefectiveResolution::Refund,
                'reason' => $reason,
                'created_by' => auth()->id(),
            ]);

            if ($replace) {
                $note = __('Remplacement du produit défectueux (dossier #:id).', ['id' => $defective->id]);

                if ($line->is_custom) {
                    $replacement = $this->addCustomProduct($line->product, $quantity, (string) $line->description, (float) $line->unit_cost, $line->unit_price, $line->quote_number);
                    $replacement->update(['note' => $note]);
                } else {
                    $this->addProduct($line->product, $quantity, $line->unit_price, $note);
                }
            } else {
                $this->recordPayments($refunds, CustomerPaymentType::Refund);
            }

            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $defective;
        });
    }

    private function guardDefectiveQuantity(CustomerOrderLine $line, int $quantity): void
    {
        if ($line->isService() || $line->isPart() || ! $line->status->isHandedOver()) {
            throw new DomainException(__('Seul un produit livré, ramassé ou expédié peut être déclaré défectueux.'));
        }

        if ($quantity < 1 || $quantity > $line->quantity) {
            throw new DomainException(__('La quantité défectueuse doit être entre 1 et :max.', ['max' => $line->quantity]));
        }
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
     * Passe la commande à « Ramassée » ou « Livrée » une fois toutes ses lignes facturables remises au client (les
     * services complétés ne comptent pas pour le choix entre les deux); une
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

        $done = fn (CustomerOrderLineStatus $handedOver): bool => $statuses->contains($handedOver)
            && $statuses->every(fn (CustomerOrderLineStatus $status): bool => in_array($status, [$handedOver, CustomerOrderLineStatus::Completed], true));

        if ($done(CustomerOrderLineStatus::PickedUp)) {
            $this->update(['status' => CustomerOrderStatus::PickedUp]);
        } elseif ($done(CustomerOrderLineStatus::Delivered)) {
            $this->update(['status' => CustomerOrderStatus::Delivered]);
        } elseif (in_array($this->status, [CustomerOrderStatus::PickedUp, CustomerOrderStatus::Delivered], true)) {
            $this->update(['status' => CustomerOrderStatus::Pending]);
        }
    }

    private function handOver(CustomerOrderLine $line, int $quantity, CustomerOrderPickup $pickup): void
    {
        if (! $line->isPart()) {
            InventoryMovement::record($line->product_id, InventoryStatus::ReservedCustomer, null, $quantity, InventoryMovementType::CustomerPickup, $this);

            InventoryUnit::query()
                ->where('product_id', $line->product_id)
                ->whereNull('delivered_at')
                ->fifo()
                ->limit($quantity)
                ->get()
                ->each(fn (InventoryUnit $unit) => $unit->update(['delivered_at' => today()]));
        }

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
            ...$line->saleAttributes(),
            ...$pickedUp,
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
