<?php

namespace App\Models;

use App\Actions\NotifyDeliveryItemsReceived;
use App\Actions\NotifyDeliveryTeam;
use App\Actions\NotifyShipmentReady;
use App\Casts\MoneyCast;
use App\Concerns\BelongsToStore;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\DefectiveResolution;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Enums\UndeliveredReason;
use App\Support\Money;
use Database\Factories\CustomerOrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Money $subtotal
 * @property Money $total
 * @property Money $amount_paid
 * @property Money $balance_due
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read customer $customer
 * @property-read Store|null $store
 * @property-read User|null $creator
 * @property-read Collection<int, User> $salespeople
 * @property-read Collection<int, CustomerOrderLine> $lines
 * @property-read Collection<int, CustomerOrderPickup> $pickups
 * @property-read Collection<int, CustomerOrderDelivery> $deliveries
 * @property-read Collection<int, CustomerOrderPayment> $payments
 * @property-read Collection<int, CustomerOrderTax> $taxLines
 */
#[Fillable(['customer_id', 'store_id', 'status', 'subtotal', 'total', 'amount_paid', 'balance_due', 'created_by'])]
class CustomerOrder extends Model
{
    use BelongsToStore;

    /** @use HasFactory<CustomerOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'subtotal' => 0,
        'total' => 0,
        'amount_paid' => 0,
        'balance_due' => 0,
    ];

    protected $casts = [
        'status' => CustomerOrderStatus::class,
        'balance_due' => MoneyCast::class,
        'subtotal' => MoneyCast::class,
        'total' => MoneyCast::class,
        'amount_paid' => MoneyCast::class,
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

    /** @return HasMany<CustomerOrderDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CustomerOrderDelivery::class);
    }

    /**
     * Magasin dont le stock sert cette commande (ADR 0012) : l'entrepôt du magasin de la commande s'il en a un, sinon
     * le magasin lui-même. Réservations, ramassages, livraisons, expéditions et retours s'y font.
     *
     * @throws DomainException si la commande n'a pas de magasin.
     */
    public function stockStoreId(): int
    {
        if ($this->store_id === null) {
            throw new DomainException(__('La commande n\'est liée à aucun magasin : impossible de toucher à son inventaire.'));
        }

        $store = $this->relationLoaded('store') ? $this->store : $this->store()->first();

        return $store?->stockStoreId() ?? $this->store_id;
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
    public function addProduct(Product $product, int $quantity = 1, ?Money $unitPrice = null, ?string $note = null): CustomerOrderLine
    {
        if ($product->is_custom) {
            throw new DomainException(__('Produit sur mesure : saisissez ses spécifications, son coût soumis et son prix.'));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        return DB::transaction(function () use ($product, $quantity, $unitPrice, $note): CustomerOrderLine {
            $line = $unitPrice !== null
                ? $this->lines()->make(['product_id' => $product->id, 'unit_price' => $unitPrice, 'is_taxable' => $product->is_taxable, 'note' => $note])
                : $this->lines()
                    ->where('product_id', $product->id)
                    ->whereIn('status', [CustomerOrderLineStatus::InStock, CustomerOrderLineStatus::OnOrder])
                    ->lockForUpdate()
                    ->first()
                    ?? $this->lines()->make(['product_id' => $product->id, 'unit_price' => $product->selling_price, 'is_taxable' => $product->is_taxable]);

            $available = InventoryStock::query()->lockForUpdate()->where('product_id', $product->id)->where('store_id', $this->stockStoreId())->value('quantity_in_stock') ?? 0;
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
     * commande du fournisseur de la pièce. La ligne en commande existante de la pièce est augmentée, sinon une ligne
     * est créée à son prix de vente calculé, figé sur la ligne (il ne suit pas les changements de coût).
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
                ?? $this->lines()->make(['part_id' => $part->id, 'unit_price' => $part->selling_price, 'note' => $note]);

            $this->fillLineQuantities($line, 0, $line->quantity_on_order + $quantity);
            $line->save();

            $this->syncSupplierLine($line);
            $this->recalculateBalance();
            $this->refreshStatusFromLines();

            return $line;
        });
    }

    /**
     * Met à jour une ligne de pièce : quantité tant qu'elle n'est pas commandée au fournisseur (la ligne de commande
     * fournisseur suit), prix et « sans frais » (prix à 0 $) tant que la pièce n'est pas remise au client (elle est
     * alors payée à 100 %), note en tout temps.
     *
     * @throws DomainException si la ligne n'est pas une pièce, si la quantité ou le prix ne peut plus changer.
     */
    public function updatePartLine(CustomerOrderLine $line, int $onOrder, Money $unitPrice, bool $isNoCharge, ?string $note): void
    {
        DB::transaction(function () use ($line, $onOrder, $unitPrice, $isNoCharge, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->isPart()) {
                throw new DomainException(__('Cette ligne n\'est pas une pièce.'));
            }

            $unitPrice = $isNoCharge ? Money::zero() : $unitPrice;

            if ($onOrder !== $line->quantity_on_order) {
                if (! $line->status->isEditable()) {
                    throw new DomainException(__('Cette pièce est déjà commandée au fournisseur : sa quantité ne peut plus changer.'));
                }

                $this->adjustLine($line, $line->quantity_reserved, $onOrder);
                $line->refresh();
            }

            $changesPrice = ! $unitPrice->equals($line->unit_price) || $isNoCharge !== $line->is_no_charge;

            if ($changesPrice && ($line->status->isHandedOver() || ! $line->status->isBillable())) {
                throw new DomainException(__('Cette pièce est remise au client ou annulée : son prix ne change plus.'));
            }

            $line->update([
                'unit_price' => $unitPrice,
                'is_no_charge' => $isNoCharge,
                'note' => filled($note) ? $note : null,
            ]);

            $this->recalculateBalance();
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

            $this->guardNotInTransfer($line);

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
            $this->syncDeliveries();
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

            $this->syncDeliveries();
            $this->recalculateBalance();
        });
    }

    /**
     * Répercute une réception de marchandise : les unités reçues sont aussitôt réservées au client (elles ne sont
     * pas disponibles pour les autres) et passent de « en commande » à « en stock » sur la ligne. Une fois tout reçu,
     * la ligne passe à « Reçu ». Les livreurs sont avisés si la ligne est prévue dans une livraison à venir.
     */
    public function applySupplierReceipt(CustomerOrderLine $line, int $quantity): void
    {
        $received = DB::transaction(function () use ($line, $quantity): int {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);
            $quantity = min($quantity, $line->quantity_on_order);

            if ($quantity < 1) {
                return 0;
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

            return $quantity;
        });

        app(NotifyDeliveryItemsReceived::class)->handle($line, $received);
    }

    /**
     * Répercute des articles reçus endommagés : ils ne sont pas remis au client, la quantité est donc recommandée
     * sur le brouillon du fournisseur (ligne « En commande » distincte si une partie de la ligne est déjà en main).
     */
    public function applySupplierDamagedReceipt(CustomerOrderLine $line, int $quantity): void
    {
        $this->reorderFromSupplier($line, $quantity);
    }

    /**
     * Répercute des articles manquants à la réception (facturés par le fournisseur, mais jamais reçus; un crédit est
     * demandé) : la quantité est recommandée sur le brouillon du fournisseur, comme un article reçu endommagé.
     */
    public function applySupplierShortShipment(CustomerOrderLine $line, int $quantity): void
    {
        $this->reorderFromSupplier($line, $quantity);
    }

    /**
     * Recommande sur le brouillon du fournisseur une quantité qui ne sera pas remise au client par la commande
     * fournisseur actuelle (ligne « En commande » distincte si une partie de la ligne est déjà en main).
     */
    private function reorderFromSupplier(CustomerOrderLine $line, int $quantity): void
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
            $this->syncDeliveries(reorderedLines: [$line->id => $reorder->id]);
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
    public function cancellationFeeFor(CustomerOrderLine $line, int $quantity): Money
    {
        return $line->unit_price->times($quantity)->percent($this->store->cancellation_fee_percent ?? 0);
    }

    /**
     * Le client annule, sans attendre la réponse du fournisseur, ce qui reste à recevoir d'une ligne commandée : la
     * partie en commande devient une ligne « Annulé » qui porte les frais d'annulation du magasin, et elle n'est
     * plus liée à la commande fournisseur (ce qui arrivera entre en stock disponible). Une demande d'annulation est
     * envoyée au fournisseur si elle ne l'a pas déjà été. La partie déjà en stock ou reçue reste au client.
     *
     * @return Money Frais d'annulation facturés.
     *
     * @throws DomainException si la ligne n'est pas commandée ou en demande d'annulation.
     */
    public function cancelLineWithFee(CustomerOrderLine $line): Money
    {
        $supplierLine = null;

        $fee = DB::transaction(function () use ($line, &$supplierLine): Money {
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

            $this->syncDeliveries();
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

            $this->syncDeliveries();
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

                $this->syncDeliveries(withdrawnLineIds: [$line->id]);
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

            $this->syncDeliveries();
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
    public function updateLine(CustomerOrderLine $line, int $reserved, int $onOrder, Money $unitPrice, ?string $note): void
    {
        DB::transaction(function () use ($line, $reserved, $onOrder, $unitPrice, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            $changesSale = $reserved !== $line->quantity_reserved
                || $onOrder !== $line->quantity_on_order
                || ! $unitPrice->equals($line->unit_price);

            if ($changesSale) {
                if (! $line->status->isEditable()) {
                    throw new DomainException(__('Les quantités et le prix de cette ligne ne peuvent plus être modifiés.'));
                }

                $this->adjustLine($line, $reserved, $onOrder);
                $line->refresh();
            }

            $line->update([
                'unit_price' => $unitPrice,
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

            $this->guardNotInTransfer($line);

            if ($line->quantity_reserved > 0) {
                $this->moveReservation($line->product_id, InventoryStatus::ReservedCustomer, InventoryStatus::InStock, $line->quantity_reserved);
            }

            $this->detachSupplierLine($line);
            $this->syncDeliveries(withdrawnLineIds: [$line->id]);
            $line->delete();

            $this->recalculateBalance();
        });
    }

    /**
     * Garde chaque livraison active dans ce qui reste à remettre au client. La quantité prévue d'une ligne est réduite à
     * la quantité de la ligne (0 si elle est annulée, retirée ou remplacée par un autre produit), en commençant par la
     * livraison la plus tardive (les planifiées avant celles en route). Une livraison qui n'a plus d'articles est
     * annulée. Les livreurs sont avisés une fois la transaction terminée.
     *
     * @param  list<int>  $withdrawnLineIds  lignes à retirer des livraisons même si leur quantité reste (substitution, retrait)
     * @param  array<int, int>  $reorderedLines  nouvelle ligne recommandée au fournisseur, par ligne d'origine : la quantité
     *                                           retirée de la ligne d'origine y est reportée, dans la même livraison
     */
    private function syncDeliveries(array $withdrawnLineIds = [], array $reorderedLines = []): void
    {
        $plannedLines = CustomerOrderDeliveryLine::query()
            ->with('delivery')
            ->whereHas('delivery', fn (Builder $query) => $query->active()->where('customer_order_id', $this->id))
            ->get();

        if ($plannedLines->isEmpty()) {
            return;
        }

        $orderLines = $this->lines()->get()->keyBy('id');

        /** @var array<int, array{delivery: CustomerOrderDelivery, changes: list<string>}> $changedDeliveries */
        $changedDeliveries = [];

        foreach ($plannedLines->groupBy('customer_order_line_id') as $lineId => $planned) {
            $line = $orderLines->get($lineId);
            $deliverable = $line === null || in_array($lineId, $withdrawnLineIds, true) || $line->isService()
                || ! ($line->status->isPickable() || $line->status === CustomerOrderLineStatus::InDelivery)
                ? 0
                : $line->quantity;
            $excess = $planned->sum('quantity') - $deliverable;

            $latestFirst = $planned->sortByDesc(fn (CustomerOrderDeliveryLine $plannedLine): string => implode('|', [
                $plannedLine->delivery->status === DeliveryStatus::Scheduled ? 1 : 0,
                $plannedLine->delivery->scheduled_date?->toDateString() ?? '',
                $plannedLine->delivery->period->value ?? '',
                str_pad((string) $plannedLine->delivery->id, 10, '0', STR_PAD_LEFT),
            ]));

            foreach ($latestFirst as $plannedLine) {
                if ($excess < 1) {
                    break;
                }

                $moved = min($excess, $plannedLine->quantity);
                $excess -= $moved;
                $label = $line === null ? '' : $line->label;

                if (isset($reorderedLines[$lineId])) {
                    $reordered = $plannedLine->delivery->lines()->firstOrCreate(['customer_order_line_id' => $reorderedLines[$lineId]], ['quantity' => 0]);
                    $reordered->increment('quantity', $moved);
                    $change = __(':quantity × :label recommandé(s) au fournisseur', ['quantity' => $moved, 'label' => $label]);
                } else {
                    $change = __(':quantity × :label retiré(s)', ['quantity' => $moved, 'label' => $label]);
                }

                if ($moved === $plannedLine->quantity) {
                    $plannedLine->delete();
                } else {
                    $plannedLine->decrement('quantity', $moved);
                }

                $changedDeliveries[$plannedLine->delivery->id]['delivery'] = $plannedLine->delivery;
                $changedDeliveries[$plannedLine->delivery->id]['changes'][] = $change;
            }
        }

        foreach ($changedDeliveries as ['delivery' => $delivery, 'changes' => $changes]) {
            $isEmpty = $delivery->lines()->doesntExist();

            if ($isEmpty) {
                $delivery->update([
                    'status' => DeliveryStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => __('Plus aucun article à livrer'),
                ]);
            }

            DB::afterCommit(fn () => app(NotifyDeliveryTeam::class)->handle(
                $delivery,
                match (true) {
                    $delivery->method === DeliveryMethod::Shipping => $isEmpty ? __('Expédition annulée') : __('Expédition modifiée'),
                    $isEmpty => __('Livraison annulée : :slot', ['slot' => $delivery->slotLabel()]),
                    default => __('Livraison modifiée : :slot', ['slot' => $delivery->slotLabel()]),
                },
                $isEmpty ? __('Plus aucun article à livrer (:changes)', ['changes' => implode(', ', $changes)]) : implode(', ', $changes),
            ));

            if (! $isEmpty && $delivery->method === DeliveryMethod::Shipping) {
                DB::afterCommit(fn () => app(NotifyShipmentReady::class)->handle($delivery->fresh()));
            }
        }
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
            $unitCost = $line->is_custom ? ($line->unit_cost ?? Money::zero()) : $supplierLine->unit_cost;

            if ($supplierLine->quantity !== $line->quantity_on_order || ! $unitCost->equals($supplierLine->unit_cost)) {
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
            'unit_cost' => $line->is_custom ? ($line->unit_cost ?? Money::zero()) : $product->cost,
        ]);

        $line->update(['supplier_order_line_id' => $supplierLine->id]);
    }

    /**
     * Brouillon de commande du fournisseur, du type voulu (produits ou services), à livrer au magasin qui tient le
     * stock de cette commande (ADR 0012), créé au besoin.
     */
    private function supplierDraftFor(int $supplierId, SupplierType $type): SupplierOrder
    {
        return SupplierOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('store_id', $this->stockStoreId())
            ->where('type', $type)
            ->where('status', SupplierOrderStatus::Draft)
            ->lockForUpdate()
            ->first()
            ?? SupplierOrder::create([
                'store_id' => $this->stockStoreId(),
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
            $this->stockStoreId(),
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
        $taxable = Money::sum($billable->filter(fn (CustomerOrderLine $line): bool => $line->is_taxable)->map(fn (CustomerOrderLine $line): Money => $line->total))
            ->plus(Money::sum($lines->map(fn (CustomerOrderLine $line): Money => $line->cancellation_fee ?? Money::zero())));
        $exempt = Money::sum($billable->reject(fn (CustomerOrderLine $line): bool => $line->is_taxable)->map(fn (CustomerOrderLine $line): Money => $line->total));
        $taxes = $this->taxesFor($taxable);
        $total = $taxes['total']->plus($exempt);
        $amountPaid = Money::of((int) $this->payments()->sum('amount'));

        foreach ($this->taxLines as $taxLine) {
            $taxLine->update(['amount' => $taxes['amounts'][$taxLine->id]]);
        }

        $this->update([
            'subtotal' => $taxable->plus($exempt),
            'total' => $total,
            'amount_paid' => $amountPaid,
            'balance_due' => $total->minus($amountPaid),
        ]);

        $this->postSales($lines, $amountPaid);
    }

    /**
     * Total des ventes comptabilisées de la commande, taxes incluses : lignes remises et frais d'annulation (ADR 0016).
     */
    public function recognizedSales(): Money
    {
        return $this->recognition($this->lines()->get())['total'];
    }

    /**
     * Ventes comptabilisées : lignes remises au client, frais d'annulation, taxes sur leur partie taxable et total.
     *
     * @param  Collection<int, CustomerOrderLine>  $lines
     * @return array{handedOver: Collection<int, CustomerOrderLine>, fees: Money, taxes: array{amounts: array<int, Money>, total: Money}, total: Money}
     */
    private function recognition(Collection $lines): array
    {
        $handedOver = $lines->filter(fn (CustomerOrderLine $line): bool => $line->status->isHandedOver());
        $fees = Money::sum($lines->map(fn (CustomerOrderLine $line): Money => $line->cancellation_fee ?? Money::zero()));
        $taxable = Money::sum($handedOver->filter(fn (CustomerOrderLine $line): bool => $line->is_taxable)->map(fn (CustomerOrderLine $line): Money => $line->total))->plus($fees);
        $taxes = $this->taxesFor($taxable);

        return [
            'handedOver' => $handedOver,
            'fees' => $fees,
            'taxes' => $taxes,
            'total' => Money::sum($handedOver->map(fn (CustomerOrderLine $line): Money => $line->total))->plus($fees)->plus(Money::sum($taxes['amounts'])),
        ];
    }

    /**
     * Met le grand livre à jour avec les ventes de la commande (ADR 0016) : passe l'écart entre ce que la commande
     * a comptabilisé jusqu'ici (écritures qui la référencent) et ce qu'elle doit avoir comptabilisé maintenant.
     *
     * - Ventes : lignes remises au client (ramassées, livrées, expédiées, services complétés) et frais d'annulation,
     *   aux Ventes de marchandises, de services ou aux Frais d'annulation; les taxes sur la partie taxable, aux taxes
     *   à payer. Le total est pris aux Dépôts de clients, et aux Comptes clients pour ce qui n'est pas payé.
     * - Coût : valeur FIFO des unités sorties pour la commande, moins celle des unités rouvertes par un retour, au
     *   Coût des marchandises vendues contre le Stock. Le coût des pièces et des services est passé à la facture du
     *   fournisseur.
     *
     * Le calcul est cumulatif : un retour renverse la vente et le coût, et les taxes, recalculées sur le cumul,
     * égalent celles de la commande une fois tout remis.
     *
     * @param  Collection<int, CustomerOrderLine>  $lines
     */
    private function postSales(Collection $lines, Money $amountPaid): void
    {
        ['handedOver' => $handedOver, 'fees' => $fees, 'taxes' => $taxes, 'total' => $sales] = $this->recognition($lines);
        $receivable = $sales->minus($amountPaid)->max(Money::zero());

        $cost = Money::sum(InventoryMovement::query()
            ->whereMorphedTo('reference', $this)
            ->whereNotNull('value')
            ->get()
            ->map(fn (InventoryMovement $movement): Money => $movement->to_status?->isValued() ? $movement->value->negate() : $movement->value)
            ->all());

        $target = [
            [LedgerAccount::forKey(LedgerAccount::MERCHANDISE_SALES), Money::sum($handedOver->reject(fn (CustomerOrderLine $line): bool => $line->isService())->map(fn (CustomerOrderLine $line): Money => $line->total))->negate()],
            [LedgerAccount::forKey(LedgerAccount::SERVICE_SALES), Money::sum($handedOver->filter(fn (CustomerOrderLine $line): bool => $line->isService())->map(fn (CustomerOrderLine $line): Money => $line->total))->negate()],
            [LedgerAccount::forKey(LedgerAccount::CANCELLATION_FEES), $fees->negate()],
            [LedgerAccount::forKey(LedgerAccount::CUSTOMER_DEPOSITS), $sales->minus($receivable)],
            [LedgerAccount::forKey(LedgerAccount::ACCOUNTS_RECEIVABLE), $receivable],
            [LedgerAccount::forKey(LedgerAccount::COST_OF_GOODS_SOLD), $cost],
            [LedgerAccount::forKey(LedgerAccount::INVENTORY), $cost->negate()],
        ];

        foreach ($this->taxLines as $taxLine) {
            $target[] = [LedgerAccount::forTaxPayable($taxLine->name), $taxes['amounts'][$taxLine->id]->negate()];
        }

        $posted = LedgerEntryLine::query()
            ->whereHas('entry', fn (Builder $entry) => $entry->whereMorphedTo('reference', $this))
            ->with('account')
            ->get();

        foreach ($posted as $line) {
            $target[] = [$line->account, $line->credit->minus($line->debit)];
        }

        $entryLines = LedgerEntry::signedLines($target);

        if ($entryLines !== []) {
            LedgerEntry::post(__('Ventes — commande #:id', ['id' => $this->id]), $entryLines, $this, storeId: $this->store_id);
        }
    }

    /**
     * Taxes de la commande sur un montant avant taxes, arrondies au cent chacune. Les taxes individuelles se
     * calculent sur le montant; celles en cascade sur le montant plus les taxes individuelles.
     *
     * @return array{amounts: array<int, Money>, total: Money} montant par taxe (clé : id de la ligne de taxe) et montant taxes incluses
     */
    public function taxesFor(Money $amount): array
    {
        $amounts = [];
        $individual = Money::zero();

        foreach ($this->taxLines->sortBy('is_compound') as $taxLine) {
            $base = $taxLine->is_compound ? $amount->plus($individual) : $amount;
            $amounts[$taxLine->id] = $base->percent($taxLine->rate);

            if (! $taxLine->is_compound) {
                $individual = $individual->plus($amounts[$taxLine->id]);
            }
        }

        return ['amounts' => $amounts, 'total' => $amount->plus(Money::sum($amounts))];
    }

    /**
     * Montant à encaisser pour remettre ces quantités au client : les articles remis (déjà ou maintenant) doivent
     * être payés à 100 % et le reste de la commande couvert par le dépôt minimum, taxes incluses, moins ce qui est
     * déjà payé.
     *
     * @param  array<int, int>  $quantities  quantité à remettre par ligne
     */
    public function amountRequiredFor(array $quantities): Money
    {
        $required = $this->requiredPaidTotal(
            fn (CustomerOrderLine $line): Money => $line->status->isHandedOver() ? $line->total : $line->unit_price->times($quantities[$line->id] ?? 0),
        );

        $this->refresh();

        return $required->minus($this->amount_paid)->min($this->balance_due)->max(Money::zero());
    }

    /**
     * Montant à encaisser avant de planifier une livraison : la règle du ramassage appliquée aux articles de cette
     * livraison et de celles déjà prévues (ils seront remis au client), moins ce qui est déjà payé.
     *
     * @param  array<int, int>  $quantities  quantité à livrer par ligne
     */
    public function amountRequiredForDelivery(array $quantities, ?int $ignoringDeliveryId = null): Money
    {
        $total = $this->deliveringQuantities($ignoringDeliveryId);

        foreach ($quantities as $lineId => $quantity) {
            $total[$lineId] = ($total[$lineId] ?? 0) + $quantity;
        }

        return $this->amountRequiredFor($total);
    }

    /**
     * Montant à encaisser avant de demander une expédition : les articles expédiés (et ceux des autres livraisons
     * actives) et les frais d'expédition pas encore remis comptent à 100 %, le dépôt sur le reste, moins le payé.
     *
     * @param  array<int, int>  $quantities  quantité à expédier par ligne
     */
    public function amountRequiredForShipment(array $quantities): Money
    {
        foreach ($this->shippingFeeLines() as $feeLine) {
            $quantities[$feeLine->id] = ($quantities[$feeLine->id] ?? 0) + $feeLine->quantity;
        }

        return $this->amountRequiredForDelivery($quantities);
    }

    /**
     * Lignes de frais d'expédition (service géré par l'application) facturables et pas encore complétées.
     *
     * @return Collection<int, CustomerOrderLine>
     */
    public function shippingFeeLines(): Collection
    {
        return $this->lines()
            ->whereHas('service', fn (Builder $query) => $query->where('code', Service::SHIPPING_FEE))
            ->get()
            ->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable() && ! $line->status->isHandedOver())
            ->values();
    }

    /**
     * Ajoute les frais d'expédition à la commande, au prix fixe du service.
     *
     * @throws DomainException si la commande en a déjà.
     */
    public function addShippingFee(): CustomerOrderLine
    {
        if ($this->shippingFeeLines()->isNotEmpty()) {
            throw new DomainException(__('Les frais d\'expédition sont déjà sur la commande.'));
        }

        $service = Service::shippingFee();

        return $this->addService($service, null, 1, $service->name);
    }

    /**
     * Sépare d'une ligne la quantité qu'un transfert d'un autre magasin servira (issue #103) : elle est retirée de la
     * commande au fournisseur (son brouillon n'est pas encore envoyé) et devient une ligne « En commande » sans ligne
     * fournisseur, liée au transfert. Retourne cette ligne.
     *
     * @throws DomainException si l'article n'est pas un produit inventorié, si la quantité dépasse ce qui est en
     *                         commande ou si la commande fournisseur est déjà envoyée.
     */
    public function takeLineForTransfer(CustomerOrderLine $line, int $quantity): CustomerOrderLine
    {
        return DB::transaction(function () use ($line, $quantity): CustomerOrderLine {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if ($line->product_id === null || $line->isPart() || $line->isService() || $line->is_custom) {
                throw new DomainException(__('Seul un produit du catalogue se transfère d\'un autre magasin.'));
            }

            $supplierLine = $line->supplierOrderLine()->with('order')->first();

            if ($supplierLine !== null && ! $supplierLine->order->status->isEditable()) {
                throw new DomainException(__('La commande fournisseur :number est déjà envoyée : annulez d\'abord la ligne chez le fournisseur.', ['number' => $supplierLine->order->number]));
            }

            if (! in_array($line->status, [CustomerOrderLineStatus::OnOrder, CustomerOrderLineStatus::InStock], true) || $quantity < 1 || $quantity > $line->quantity_on_order) {
                throw new DomainException(__('On peut transférer au plus la quantité en commande de la ligne (:count).', ['count' => $line->quantity_on_order]));
            }

            if ($quantity === $line->quantity && $line->quantity_reserved === 0) {
                $this->detachSupplierLine($line);

                return $line->refresh();
            }

            $this->fillLineQuantities($line, $line->quantity_reserved, $line->quantity_on_order - $quantity);
            $line->save();
            $this->syncSupplierLine($line);

            $transferLine = $this->lines()->create([
                ...$line->saleAttributes(),
                'inventory_location_id' => $line->inventory_location_id,
                'quantity' => $quantity,
                'quantity_reserved' => 0,
                'quantity_on_order' => $quantity,
                'status' => CustomerOrderLineStatus::OnOrder,
            ]);

            $this->syncDeliveries();

            return $transferLine;
        });
    }

    /**
     * La ligne d'un transfert refusé ou annulé est commandée au fournisseur, comme toute ligne « En commande ».
     */
    public function returnLineFromTransfer(CustomerOrderLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);
            $this->syncSupplierLine($line);
        });
    }

    /**
     * La ligne d'un transfert reçu passe « Transfert reçu » : la quantité reçue lui est réservée dans le magasin qui
     * reçoit. Un écart (moins reçu qu'envoyé) devient une ligne « En commande », commandée au fournisseur.
     *
     * @return CustomerOrderLine|null la ligne commandée pour l'écart, s'il y a lieu
     */
    public function receiveTransferLine(CustomerOrderLine $line, int $received): ?CustomerOrderLine
    {
        return DB::transaction(function () use ($line, $received): ?CustomerOrderLine {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);
            $short = $line->quantity - $received;
            $reordered = null;

            if ($short > 0) {
                $reordered = $this->lines()->create([
                    ...$line->saleAttributes(),
                    'quantity' => $short,
                    'quantity_reserved' => 0,
                    'quantity_on_order' => $short,
                    'status' => CustomerOrderLineStatus::OnOrder,
                ]);
                $this->syncSupplierLine($reordered);
            }

            if ($received > 0) {
                $line->update(['quantity' => $received, 'quantity_reserved' => $received, 'quantity_on_order' => 0, 'status' => CustomerOrderLineStatus::TransferReceived]);
            } else {
                $line->delete();
            }

            $this->syncDeliveries();

            return $reordered;
        });
    }

    /** Une ligne servie par un transfert ouvert ne se modifie pas : il faut d'abord annuler le transfert. */
    private function guardNotInTransfer(CustomerOrderLine $line): void
    {
        if ($line->transfers()->open()->exists()) {
            throw new DomainException(__('Cet article est en transfert d\'un autre magasin : annulez d\'abord le transfert.'));
        }
    }

    /**
     * Note (ou efface, avec null) l'emplacement de l'entrepôt où une ligne est rangée (issue #96). Pour information
     * seulement : l'emplacement ne tient pas de quantités.
     *
     * @throws DomainException si la ligne est un service ou si l'emplacement n'est pas un emplacement actif du
     *                         magasin de la commande.
     */
    public function placeLine(CustomerOrderLine $line, ?int $locationId): void
    {
        if ($line->customer_order_id !== $this->id || $line->isService()) {
            throw new DomainException(__('Seul un produit ou une pièce de la commande se range dans l\'entrepôt.'));
        }

        if ($locationId !== null && ($this->store_id === null || ! InventoryLocation::query()->ofStore($this->store_id)->selectable()->whereKey($locationId)->exists())) {
            throw new DomainException(__('Choisissez un emplacement actif du magasin de la commande.'));
        }

        $line->update(['inventory_location_id' => $locationId]);
    }

    /**
     * Emplacement proposé pour ranger une ligne : le sien, sinon l'emplacement habituel du produit dans le magasin.
     */
    public function suggestedLocationFor(CustomerOrderLine $line): ?InventoryLocation
    {
        if ($line->location !== null) {
            return $line->location;
        }

        return $line->product !== null && $this->store_id !== null ? $line->product->habitualLocationFor($this->store_id) : null;
    }

    /**
     * Valide les quantités d'une nouvelle livraison ou expédition : au moins un article, et pas plus que ce qui reste à
     * remettre au client (deliverableQuantities()).
     *
     * @param  array<int, int>  $quantities  quantité par ligne
     * @return array<int, int> les quantités positives
     *
     * @throws DomainException
     */
    public function guardDeliverableQuantities(array $quantities): array
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);

        if ($quantities === []) {
            throw new DomainException(__('Choisissez au moins un article à livrer.'));
        }

        $deliverable = $this->deliverableQuantities();

        foreach ($quantities as $lineId => $quantity) {
            if ($quantity > ($deliverable[$lineId] ?? 0)) {
                throw new DomainException(__('La quantité à livrer dépasse ce qui reste à remettre au client.'));
            }
        }

        return $quantities;
    }

    /**
     * Adresse de la fiche du client, vérifiée avec lui maintenant par l'usager courant, à copier sur une livraison.
     *
     * @return array<string, mixed>
     *
     * @throws DomainException si l'adresse est incomplète.
     */
    public function verifiedDeliveryAddress(): array
    {
        $customer = $this->customer()->firstOrFail();

        if (! $customer->hasCompleteAddress()) {
            throw new DomainException(__('L\'adresse du client est incomplète : corrigez sa fiche avant de planifier la livraison.'));
        }

        return [
            ...$customer->only(CustomerOrderDelivery::ADDRESS_FIELDS),
            'address_verified_by' => auth()->id(),
            'address_verified_at' => now(),
        ];
    }

    /**
     * Quantités déjà prévues dans les livraisons actives (planifiées ou en route), par ligne.
     *
     * @return array<int, int>
     */
    public function deliveringQuantities(?int $ignoringDeliveryId = null): array
    {
        return CustomerOrderDeliveryLine::query()
            ->whereHas('delivery', fn (Builder $query) => $query->active()
                ->where('customer_order_id', $this->id)
                ->when($ignoringDeliveryId !== null, fn (Builder $query) => $query->whereKeyNot($ignoringDeliveryId)))
            ->selectRaw('customer_order_line_id, sum(quantity) as quantity')
            ->groupBy('customer_order_line_id')
            ->pluck('quantity', 'customer_order_line_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * Quantités qui peuvent encore être prévues dans une livraison, par ligne : les produits et les pièces pas encore
     * remis (reçus ou non), moins ce qui est déjà dans une livraison active. Les services ne se livrent pas.
     *
     * @return array<int, int>
     */
    public function deliverableQuantities(?int $ignoringDeliveryId = null): array
    {
        $delivering = $this->deliveringQuantities($ignoringDeliveryId);

        return $this->lines()->get()
            ->filter(fn (CustomerOrderLine $line): bool => ! $line->isService() && $line->status->isPickable())
            ->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => $line->quantity - ($delivering[$line->id] ?? 0)])
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }

    /**
     * Quantités qui peuvent être ramassées en magasin, par ligne : le stock réservé au client, sans ce qui est prévu
     * dans une livraison active.
     *
     * @return array<int, int>
     */
    public function pickableQuantities(): array
    {
        $delivering = $this->deliveringQuantities();

        return $this->lines()->orderBy('id')->get()
            ->filter(fn (CustomerOrderLine $line): bool => $line->status->isPickable())
            ->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => min($line->quantity_reserved, $line->quantity - ($delivering[$line->id] ?? 0))])
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }

    /**
     * Montant remboursable pour le retour de cette quantité : la valeur retournée (taxes incluses), sans que le payé
     * restant passe sous ce qu'exige le reste de la commande (100 % des articles remis, dépôt sur les autres).
     */
    public function refundableFor(CustomerOrderLine $line, int $quantity): Money
    {
        $returned = $line->unit_price->times($quantity);

        $required = $this->requiredPaidTotal(
            fn (CustomerOrderLine $other): Money => $other->status->isHandedOver()
                ? $other->total->minus($other->id === $line->id ? $returned : Money::zero())
                : Money::zero(),
            excludedLine: $line,
            excludedValue: $returned,
        );

        $this->refresh();

        $returnedTotal = $line->is_taxable ? $this->taxedTotal($returned, Money::zero()) : $returned;

        return $returnedTotal->min($this->amount_paid->minus($required))->max(Money::zero());
    }

    /**
     * Total que le client doit avoir payé : 100 % de la valeur remise (selon $handedOverValue par ligne) et le dépôt
     * minimum sur le reste des lignes facturables (celui du magasin pour le sur-mesure), taxes incluses sur les lignes
     * taxables. $excludedValue (de la ligne $excludedLine) est retiré du total facturable (articles en cours de retour).
     *
     * @param  callable(CustomerOrderLine): Money  $handedOverValue
     */
    private function requiredPaidTotal(callable $handedOverValue, ?CustomerOrderLine $excludedLine = null, ?Money $excludedValue = null): Money
    {
        $lines = $this->lines()->get()->filter(fn (CustomerOrderLine $line): bool => $line->status->isBillable());

        $handedOver = ['taxable' => Money::zero(), 'exempt' => Money::zero()];
        $remaining = [];

        foreach ($lines as $line) {
            $tax = $line->is_taxable ? 'taxable' : 'exempt';
            $kind = $line->is_custom ? 'custom' : 'regular';
            $handed = $handedOverValue($line);
            $excluded = $excludedLine !== null && $line->id === $excludedLine->id ? ($excludedValue ?? Money::zero()) : Money::zero();

            $handedOver[$tax] = $handedOver[$tax]->plus($handed);
            $remaining[$kind][$tax] = ($remaining[$kind][$tax] ?? Money::zero())->plus($line->total->minus($excluded)->minus($handed));
        }

        $deposits = [
            'regular' => config('sales.deposit_percent'),
            'custom' => $this->store->custom_deposit_percent ?? config('sales.deposit_percent'),
        ];

        $required = $this->taxedTotal($handedOver['taxable'], $handedOver['exempt']);

        foreach ($remaining as $kind => $amounts) {
            $required = $required->plus(
                $this->taxedTotal($amounts['taxable'] ?? Money::zero(), $amounts['exempt'] ?? Money::zero())->percent($deposits[$kind]),
            );
        }

        return $required;
    }

    /**
     * Montant taxes incluses d'une partie taxable et d'une partie non taxable.
     */
    private function taxedTotal(Money $taxable, Money $exempt): Money
    {
        return $this->taxesFor($taxable)['total']->plus($exempt);
    }

    /**
     * Remet au client les quantités choisies (prises dans le stock réservé) et encaisse le paiement exigé.
     * Une ligne remise en partie est séparée : la partie ramassée devient une ligne « Ramassé ». Les unités
     * sortent de l'inventaire (premier entré, premier sorti). Sans article, seul le paiement est encaissé
     * (aucun ramassage n'est créé).
     *
     * @param  array<int, int>  $quantities  quantité à remettre par ligne
     * @param  array<int, array{method_id: int, amount: Money, tendered?: Money|null}>  $payments  (tendered : comptant reçu)
     * @param  Money|null  $creditUsed  montant payé avec le crédit au compte du client (déduit de ce crédit)
     *
     * @throws DomainException si une quantité n'est pas disponible, si le crédit utilisé dépasse celui du client ou
     *                         si le paiement ne couvre pas le montant exigé.
     */
    public function pickUp(array $quantities, array $payments, ?Money $creditUsed = null): ?CustomerOrderPickup
    {
        $quantities = array_filter($quantities, fn (int $quantity): bool => $quantity > 0);
        $creditUsed ??= Money::zero();

        if ($creditUsed->isNegative()) {
            throw new DomainException(__('Le crédit utilisé ne peut pas être négatif.'));
        }

        $paid = Money::sum(array_column($payments, 'amount'))->plus($creditUsed);

        if ($quantities === [] && ! $paid->isPositive()) {
            throw new DomainException(__('Choisissez au moins un article à ramasser ou entrez un paiement.'));
        }

        return DB::transaction(function () use ($quantities, $payments, $creditUsed, $paid): ?CustomerOrderPickup {
            $lines = $this->lines()->lockForUpdate()->whereKey(array_keys($quantities))->get()->keyBy('id');
            $pickable = $this->pickableQuantities();

            foreach ($quantities as $lineId => $quantity) {
                $line = $lines->get($lineId);

                if ($line === null || ! $line->status->isPickable() || $quantity > ($pickable[$lineId] ?? 0)) {
                    throw new DomainException(__('La quantité à ramasser dépasse ce qui est disponible pour le client (les articles prévus dans une livraison ne se ramassent pas).'));
                }
            }

            $customer = customer::query()->whereKey($this->customer_id)->lockForUpdate()->firstOrFail();

            if ($creditUsed->greaterThan($customer->credit_balance)) {
                throw new DomainException(__('Le crédit utilisé dépasse le crédit au compte du client (:credit $).', ['credit' => $customer->credit_balance->toDecimal()]));
            }

            $required = $this->amountRequiredFor($quantities);

            if ($paid->lessThan($required)) {
                throw new DomainException(__('Le paiement (:paid $) ne couvre pas le montant exigé (:required $).', [
                    'paid' => $paid->toDecimal(),
                    'required' => $required->toDecimal(),
                ]));
            }

            if ($paid->greaterThan($this->balance_due)) {
                throw new DomainException(__('Le paiement dépasse le solde de la commande (:balance $).', ['balance' => $this->balance_due->toDecimal()]));
            }

            $pickup = null;

            if ($quantities !== []) {
                $pickup = $this->pickups()->create(['handled_by' => auth()->id()]);

                foreach ($quantities as $lineId => $quantity) {
                    $this->handOver($lines->get($lineId), $quantity, $pickup);
                }
            }

            $this->recordPayments($payments, CustomerPaymentType::Payment, $pickup);

            if ($creditUsed->isPositive()) {
                $this->payments()->create([
                    'customer_order_pickup_id' => $pickup?->id,
                    'type' => CustomerPaymentType::CreditUse,
                    'amount' => $creditUsed,
                    'received_by' => auth()->id(),
                ]);

                $customer->forceFill(['credit_balance' => $customer->credit_balance->minus($creditUsed)])->save();
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
     * @param  array<int, array{method_id: int, amount: Money}>  $refunds
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

            $refundable = $refund ? $this->refundableFor($line, $quantity) : Money::zero();
            $refunded = Money::sum(array_column($refunds, 'amount'));

            if ($refunded->greaterThan($refundable)) {
                throw new DomainException(__('Le remboursement (:refunded $) dépasse le montant remboursable (:refundable $).', [
                    'refunded' => $refunded->toDecimal(),
                    'refundable' => $refundable->toDecimal(),
                ]));
            }

            InventoryMovement::record($line->product_id, $this->stockStoreId(), null, InventoryStatus::InStock, $quantity, InventoryMovementType::CustomerReturn, $this);

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
    public function addCustomProduct(Product $product, int $quantity, string $specifications, Money $unitCost, Money $unitPrice, ?string $quoteNumber = null): CustomerOrderLine
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
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
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
    public function updateCustomLine(CustomerOrderLine $line, int $quantity, string $specifications, Money $unitCost, Money $unitPrice, ?string $quoteNumber, ?string $note): void
    {
        DB::transaction(function () use ($line, $quantity, $specifications, $unitCost, $unitPrice, $quoteNumber, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->is_custom) {
                throw new DomainException(__('Cette ligne n\'est pas un article sur mesure.'));
            }

            $quoteNumber = filled($quoteNumber) ? trim($quoteNumber) : null;
            $changesSale = $quantity !== $line->quantity
                || trim($specifications) !== (string) $line->description
                || ! $unitCost->equals($line->unit_cost ?? Money::zero())
                || ! $unitPrice->equals($line->unit_price)
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
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'quote_number' => $quoteNumber,
                ]);

                $this->syncSupplierLine($line);
                $this->syncDeliveries();
            }

            $line->update(['note' => filled($note) ? $note : null]);

            $this->recalculateBalance();
        });
    }

    private function guardCustomSale(int $quantity, string $specifications, Money $unitCost, Money $unitPrice): void
    {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité doit être d\'au moins 1.'));
        }

        if (blank($specifications)) {
            throw new DomainException(__('Indiquez les spécifications de l\'article sur mesure.'));
        }

        if ($unitCost->isNegative() || $unitPrice->isNegative()) {
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
    public function addService(Service $service, ?int $supplierId, int $quantity, string $description, ?Money $unitPrice = null): CustomerOrderLine
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
                'unit_price' => $unitPrice ?? $service->pricingFor($supplierId)['selling_price'],
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
    public function updateServiceLine(CustomerOrderLine $line, int $quantity, Money $unitPrice, string $description, ?string $note): void
    {
        DB::transaction(function () use ($line, $quantity, $unitPrice, $description, $note): void {
            $line = $this->lines()->lockForUpdate()->findOrFail($line->id);

            if (! $line->isService()) {
                throw new DomainException(__('Cette ligne n\'est pas un service.'));
            }

            $changesSale = $quantity !== $line->quantity
                || ! $unitPrice->equals($line->unit_price)
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
                    'unit_price' => $unitPrice,
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
                'unit_cost' => Money::zero(),
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
     * @param  array<int, array{method_id: int, amount: Money}>  $refunds
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

            $refundable = $replace ? Money::zero() : $this->refundableFor($line, $quantity);
            $refunded = Money::sum(array_column($refunds, 'amount'));

            if ($refunded->greaterThan($refundable)) {
                throw new DomainException(__('Le remboursement (:refunded $) dépasse le montant remboursable (:refundable $).', [
                    'refunded' => $refunded->toDecimal(),
                    'refundable' => $refundable->toDecimal(),
                ]));
            }

            InventoryMovement::record($line->product_id, $this->stockStoreId(), null, InventoryStatus::DefectiveStock, $quantity, InventoryMovementType::CustomerDefectiveReturn, $this);

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
                    $replacement = $this->addCustomProduct($line->product, $quantity, (string) $line->description, $line->unit_cost ?? Money::zero(), $line->unit_price, $line->quote_number);
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
    public function credit(): Money
    {
        return $this->balance_due->negate()->max(Money::zero());
    }

    /**
     * Rembourse tout ou partie du crédit de la commande, sur un ou plusieurs modes de paiement.
     *
     * @param  array<int, array{method_id: int, amount: Money}>  $refunds
     *
     * @throws DomainException si le remboursement est nul ou dépasse le crédit.
     */
    public function refundCredit(array $refunds): void
    {
        DB::transaction(function () use ($refunds): void {
            $this->recalculateBalance();
            $refunded = Money::sum(array_column($refunds, 'amount'));

            if (! $refunded->isPositive() || $refunded->greaterThan($this->credit())) {
                throw new DomainException(__('Le remboursement doit être entre 0,01 $ et le crédit de la commande (:credit $).', ['credit' => $this->credit()->toDecimal()]));
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
    public function transferCreditToCustomer(): Money
    {
        return DB::transaction(function (): Money {
            $this->recalculateBalance();
            $credit = $this->credit();

            if (! $credit->isPositive()) {
                throw new DomainException(__('Cette commande n\'a pas de crédit à porter au compte du client.'));
            }

            $this->payments()->create([
                'type' => CustomerPaymentType::CreditTransfer,
                'amount' => $credit->negate(),
                'received_by' => auth()->id(),
            ]);

            $customer = customer::query()->whereKey($this->customer_id)->lockForUpdate()->firstOrFail();
            $customer->forceFill(['credit_balance' => $customer->credit_balance->plus($credit)])->save();

            $this->recalculateBalance();

            return $credit;
        });
    }

    /**
     * Enregistre des montants reçus (paiement) ou rendus (remboursement, en négatif), un par mode de paiement. En
     * comptant, le montant perçu ou remis est arrondi au 5 ¢ près (l'écart est conservé) et, pour un paiement, le
     * montant reçu du client (au moins le montant arrondi) et la monnaie rendue sont enregistrés.
     *
     * @param  array<int, array{method_id: int, amount: Money, tendered?: Money|null}>  $payments
     *
     * @throws DomainException si un mode est invalide ou si le comptant reçu ne couvre pas le montant arrondi.
     */
    private function recordPayments(array $payments, CustomerPaymentType $type, ?CustomerOrderPickup $pickup = null): void
    {
        foreach ($payments as $payment) {
            if (! $payment['amount']->isPositive()) {
                continue;
            }

            $method = CustomerPaymentMethod::query()->whereKey($payment['method_id'])->where('is_active', true)->first();

            if ($method === null) {
                throw new DomainException(__('Mode de paiement invalide.'));
            }

            $amount = $payment['amount'];
            $cash = [];

            if ($method->isCash()) {
                $rounded = CustomerPaymentMethod::roundCash($amount);
                $cash = ['rounding_adjustment' => $rounded->minus($amount)];

                if ($type === CustomerPaymentType::Payment) {
                    $tendered = $payment['tendered'] ?? $rounded;

                    if ($tendered->lessThan($rounded)) {
                        throw new DomainException(__('Le comptant reçu (:tendered $) ne couvre pas le montant à percevoir (:rounded $).', [
                            'tendered' => $tendered->toDecimal(),
                            'rounded' => $rounded->toDecimal(),
                        ]));
                    }

                    $cash += ['cash_tendered' => $tendered, 'change_given' => $tendered->minus($rounded)];
                }
            }

            $recorded = $this->payments()->create([
                'customer_order_pickup_id' => $pickup?->id,
                'customer_payment_method_id' => $method->id,
                'type' => $type,
                'amount' => $type === CustomerPaymentType::Payment ? $amount : $amount->negate(),
                'received_by' => auth()->id(),
                ...$cash,
            ]);

            $this->postPaymentEntry($recorded, $method);
        }
    }

    /**
     * Écriture d'un paiement ou d'un remboursement (ADR 0016) : le montant passe aux Dépôts de clients, l'argent
     * réellement perçu ou remis (arrondi au 5 ¢ en comptant) au compte du mode de paiement, l'écart aux Arrondis au
     * comptant.
     */
    private function postPaymentEntry(CustomerOrderPayment $payment, CustomerPaymentMethod $method): void
    {
        $sign = $payment->type === CustomerPaymentType::Payment ? 1 : -1;
        $rounding = $payment->rounding_adjustment ?? Money::zero();

        LedgerEntry::post(
            __(':type — commande #:id (:method)', ['type' => $payment->type->label(), 'id' => $this->id, 'method' => $method->name]),
            LedgerEntry::signedLines([
                [$method->cashAccount(), $payment->amount->plus($rounding->times($sign))],
                [LedgerAccount::forKey(LedgerAccount::CUSTOMER_DEPOSITS), $payment->amount->negate()],
                [LedgerAccount::forKey(LedgerAccount::CASH_ROUNDING), $rounding->times(-$sign)],
            ]),
            $payment,
            storeId: $this->store_id,
        );
    }

    /**
     * Passe la commande à « Livrée » une fois toutes ses lignes facturables remises au client, dès qu'une a été livrée
     * ou expédiée, sinon à « Ramassée » (les services complétés ne comptent pas pour le choix entre les deux); une
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

        $allHandedOver = $statuses->every(fn (CustomerOrderLineStatus $status): bool => $status->isHandedOver());
        $reachedCustomer = $statuses->contains(fn (CustomerOrderLineStatus $status): bool => in_array($status, [CustomerOrderLineStatus::Delivered, CustomerOrderLineStatus::Shipped], true));

        if ($allHandedOver && $reachedCustomer) {
            $this->update(['status' => CustomerOrderStatus::Delivered]);
        } elseif ($allHandedOver && $statuses->contains(CustomerOrderLineStatus::PickedUp)) {
            $this->update(['status' => CustomerOrderStatus::PickedUp]);
        } elseif (in_array($this->status, [CustomerOrderStatus::PickedUp, CustomerOrderStatus::Delivered], true)) {
            $this->update(['status' => CustomerOrderStatus::Pending]);
        }
    }

    private function handOver(CustomerOrderLine $line, int $quantity, CustomerOrderPickup $pickup): void
    {
        if (! $line->isPart()) {
            InventoryMovement::record($line->product_id, $this->stockStoreId(), InventoryStatus::ReservedCustomer, null, $quantity, InventoryMovementType::CustomerPickup, $this);
        }

        $this->splitOff($line, $quantity, [
            'quantity_reserved' => 0,
            'status' => CustomerOrderLineStatus::PickedUp,
            'customer_order_pickup_id' => $pickup->id,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Sépare une quantité réservée d'une ligne et lui donne de nouveaux attributs : la ligne entière si elle part en
     * entier (rien d'autre réservé ni en commande), sinon une nouvelle ligne de vente, l'originale gardant le reste. La
     * nouvelle ligne garde l'emplacement de l'originale.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function splitOff(CustomerOrderLine $line, int $quantity, array $attributes): CustomerOrderLine
    {
        $attributes = ['quantity' => $quantity, 'quantity_on_order' => 0, ...$attributes];

        if ($quantity === $line->quantity_reserved && $line->quantity_on_order === 0) {
            $line->update($attributes);

            return $line;
        }

        $line->update([
            'quantity_reserved' => $line->quantity_reserved - $quantity,
            'quantity' => $line->quantity - $quantity,
        ]);

        return $this->lines()->create([
            ...$line->saleAttributes(),
            'inventory_location_id' => $line->inventory_location_id,
            ...$attributes,
        ]);
    }

    /**
     * Charge dans le camion les quantités choisies d'une livraison planifiée (issue #94) : la partie chargée de chaque
     * ligne passe « En livraison » (séparée au besoin; elle reste réservée au client), ses unités passent de « Réservé
     * client » à « En livraison », et la livraison part « En route ». Une ligne non chargée est retirée de la
     * livraison et reste réservée au client.
     *
     * @param  array<int, int>  $quantities  quantité chargée par ligne de livraison
     *
     * @throws DomainException si la livraison n'est pas planifiée, si rien n'est chargé ou si une quantité dépasse ce
     *                         qui est en main pour le client.
     */
    public function loadDelivery(CustomerOrderDelivery $delivery, array $quantities): void
    {
        DB::transaction(function () use ($delivery, $quantities): void {
            $delivery = $this->lockedDelivery($delivery, DeliveryStatus::Scheduled, __('Seule une livraison planifiée peut être chargée.'));

            if (array_filter($quantities, fn (int $quantity): bool => $quantity > 0) === []) {
                throw new DomainException(__('Cochez au moins un article chargé dans le camion.'));
            }

            foreach ($delivery->lines()->with('line')->get() as $deliveryLine) {
                $quantity = $quantities[$deliveryLine->id] ?? 0;
                $line = $this->lines()->lockForUpdate()->findOrFail($deliveryLine->customer_order_line_id);
                $loadable = $line->status->isPickable() ? min($deliveryLine->quantity, $line->quantity_reserved) : 0;

                if ($quantity < 0 || $quantity > $loadable) {
                    throw new DomainException(__(':label : on peut charger au plus :count (en main pour le client).', ['label' => $line->label, 'count' => $loadable]));
                }

                if ($quantity === 0) {
                    $deliveryLine->delete();

                    continue;
                }

                if (! $line->isPart()) {
                    InventoryMovement::record($line->product_id, $this->stockStoreId(), InventoryStatus::ReservedCustomer, InventoryStatus::InDelivery, $quantity, InventoryMovementType::DeliveryLoaded, $delivery);
                }

                $previousStatus = $line->status;
                $loaded = $this->splitOff($line, $quantity, ['quantity_reserved' => $quantity, 'status' => CustomerOrderLineStatus::InDelivery]);

                $deliveryLine->update(['customer_order_line_id' => $loaded->id, 'quantity' => $quantity, 'loaded_from_status' => $previousStatus]);
            }

            $delivery->update(['status' => DeliveryStatus::InTransit, 'departed_at' => now(), 'departed_by' => auth()->id()]);

            $this->syncDeliveries();
        });
    }

    /**
     * Remet au client les articles d'une livraison en route (issue #94, confirmée par le client à la #93) : la
     * quantité acceptée de chaque ligne passe « Livré » et sort de l'inventaire (premier entré, premier sorti); la
     * quantité refusée retourne en stock client, avec le statut qu'avait la ligne avant le chargement. La livraison
     * passe « Livrée » et la commande, « Livrée » une fois tout remis.
     *
     * @param  array<int, int>  $accepted  quantité acceptée par ligne de livraison (absente : rien d'accepté)
     * @param  array<int, string>  $refusalReasons  raison du refus par ligne de livraison
     * @param  array<int, int|null>  $returnLocations  emplacement où l'article refusé est rangé au retour, par ligne de livraison
     * @return list<string> les articles refusés (« 1 × Modèle : raison »)
     *
     * @throws DomainException si la livraison n'est pas en route, si une quantité est invalide, si un refus n'a pas de
     *                         raison ou si les articles acceptés ne sont pas payés.
     */
    public function completeDelivery(CustomerOrderDelivery $delivery, array $accepted, array $refusalReasons = [], array $returnLocations = []): array
    {
        return DB::transaction(function () use ($delivery, $accepted, $refusalReasons, $returnLocations): array {
            $delivery = $this->lockedDelivery($delivery, DeliveryStatus::InTransit, __('Seule une livraison en route peut être remise au client.'));
            $deliveryLines = $delivery->lines()->get();
            $acceptedByLine = [];

            foreach ($deliveryLines as $deliveryLine) {
                $quantity = $accepted[$deliveryLine->id] ?? 0;

                if ($quantity < 0 || $quantity > $deliveryLine->quantity) {
                    throw new DomainException(__('La quantité acceptée doit être entre 0 et la quantité livrée.'));
                }

                if ($quantity < $deliveryLine->quantity && blank($refusalReasons[$deliveryLine->id] ?? null)) {
                    throw new DomainException(__('Indiquez la raison de chaque article refusé.'));
                }

                if ($quantity > 0) {
                    $acceptedByLine[$deliveryLine->customer_order_line_id] = $quantity;
                }
            }

            if ($acceptedByLine === []) {
                throw new DomainException(__('Cochez au moins un article accepté par le client (sinon, la livraison est non livrée).'));
            }

            $required = $this->amountRequiredFor($acceptedByLine);

            if ($required->isPositive()) {
                throw new DomainException(__('Percevez le paiement avant de remettre les articles : :amount $ à payer.', ['amount' => $required->toDecimal()]));
            }

            $refused = [];

            foreach ($deliveryLines as $deliveryLine) {
                $line = $this->lines()->lockForUpdate()->findOrFail($deliveryLine->customer_order_line_id);
                $quantity = $accepted[$deliveryLine->id] ?? 0;
                $refusedQuantity = $deliveryLine->quantity - $quantity;
                $deliveredLine = $line;

                if ($quantity > 0) {
                    if (! $line->isPart()) {
                        InventoryMovement::record($line->product_id, $this->stockStoreId(), InventoryStatus::InDelivery, null, $quantity, InventoryMovementType::CustomerDelivery, $this);
                    }

                    $deliveredLine = $this->splitOff($line, $quantity, ['quantity_reserved' => 0, 'status' => CustomerOrderLineStatus::Delivered, 'delivered_at' => now()]);
                }

                if ($refusedQuantity > 0) {
                    $this->returnFromDelivery($delivery, $deliveryLine, $line, $refusedQuantity, $returnLocations[$deliveryLine->id] ?? null);
                    $refused[] = __(':quantity × :label : :reason', ['quantity' => $refusedQuantity, 'label' => $line->label, 'reason' => trim((string) $refusalReasons[$deliveryLine->id])]);
                }

                $deliveryLine->update([
                    'customer_order_line_id' => $deliveredLine->id,
                    'accepted_quantity' => $quantity,
                    'refusal_reason' => $refusedQuantity > 0 ? trim((string) $refusalReasons[$deliveryLine->id]) : null,
                ]);
            }

            $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now(), 'delivered_by' => auth()->id()]);

            $this->refreshStatusFromLines();

            return $refused;
        });
    }

    /**
     * Envoie une expédition à faire par son transporteur (issue #95) : la quantité envoyée de chaque ligne passe
     * « Expédié » (séparée au besoin) et sort de l'inventaire (premier entré, premier sorti), l'expédition passe
     * « Expédiée » avec son numéro de suivi. Ce qui n'est pas envoyé (pas encore en main) reste une nouvelle
     * expédition à faire, sans nouveaux frais. Les frais d'expédition sont complétés au dernier envoi.
     *
     * @param  array<int, int>  $quantities  quantité envoyée par ligne d'expédition
     * @return CustomerOrderDelivery|null l'expédition qui reste à faire, s'il y a lieu
     *
     * @throws DomainException si l'expédition n'est pas à faire, sans transporteur, sans numéro de suivi, si rien
     *                         n'est envoyé, si une quantité n'est pas en main ou si l'envoi n'est pas payé.
     */
    public function shipDelivery(CustomerOrderDelivery $shipment, array $quantities, string $trackingNumber): ?CustomerOrderDelivery
    {
        return DB::transaction(function () use ($shipment, $quantities, $trackingNumber): ?CustomerOrderDelivery {
            $shipment = CustomerOrderDelivery::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->customer_order_id !== $this->id || $shipment->method !== DeliveryMethod::Shipping || $shipment->status !== DeliveryStatus::Scheduled) {
                throw new DomainException(__('Seule une expédition à faire peut être envoyée.'));
            }

            if ($shipment->shipping_supplier_id === null || $shipment->shipping_cost === null) {
                throw new DomainException(__('Choisissez le transporteur et inscrivez le coût de sa soumission avant l\'envoi.'));
            }

            if (blank($trackingNumber)) {
                throw new DomainException(__('Inscrivez le numéro de suivi du transporteur.'));
            }

            if (array_filter($quantities, fn (int $quantity): bool => $quantity > 0) === []) {
                throw new DomainException(__('Choisissez au moins un article à envoyer.'));
            }

            $shipmentLines = $shipment->lines()->get();
            $shippedByLine = [];
            $remaining = [];

            foreach ($shipmentLines as $shipmentLine) {
                $quantity = $quantities[$shipmentLine->id] ?? 0;
                $line = $this->lines()->lockForUpdate()->findOrFail($shipmentLine->customer_order_line_id);
                $inHand = $line->status->isPickable() ? min($shipmentLine->quantity, $line->quantity_reserved) : 0;

                if ($quantity < 0 || $quantity > $inHand) {
                    throw new DomainException(__(':label : on peut envoyer au plus :count (en main pour le client).', ['label' => $line->label, 'count' => $inHand]));
                }

                if ($quantity > 0) {
                    $shippedByLine[$line->id] = $quantity;
                }

                if ($quantity < $shipmentLine->quantity) {
                    $remaining[$line->id] = $shipmentLine->quantity - $quantity;
                }
            }

            foreach ($this->shippingFeeLines() as $feeLine) {
                $shippedByLine[$feeLine->id] = $feeLine->quantity;
            }

            $required = $this->amountRequiredFor($shippedByLine);

            if ($required->isPositive()) {
                throw new DomainException(__('L\'envoi n\'est pas payé : :amount $ à percevoir avant d\'expédier.', ['amount' => $required->toDecimal()]));
            }

            foreach ($shipmentLines as $shipmentLine) {
                $quantity = $quantities[$shipmentLine->id] ?? 0;

                if ($quantity === 0) {
                    $shipmentLine->delete();

                    continue;
                }

                $line = $this->lines()->findOrFail($shipmentLine->customer_order_line_id);

                if (! $line->isPart()) {
                    InventoryMovement::record($line->product_id, $this->stockStoreId(), InventoryStatus::ReservedCustomer, null, $quantity, InventoryMovementType::CustomerShipment, $this);
                }

                $shipped = $this->splitOff($line, $quantity, ['quantity_reserved' => 0, 'status' => CustomerOrderLineStatus::Shipped, 'delivered_at' => now()]);
                $shipmentLine->update(['customer_order_line_id' => $shipped->id, 'quantity' => $quantity, 'accepted_quantity' => $quantity]);
            }

            $shipment->update([
                'status' => DeliveryStatus::InTransit,
                'tracking_number' => trim($trackingNumber),
                'shipped_at' => now(),
                'shipped_by' => auth()->id(),
            ]);

            $remainder = null;

            if ($remaining !== []) {
                $remainder = $this->deliveries()->create([
                    ...$shipment->only([...CustomerOrderDelivery::ADDRESS_FIELDS, 'store_id', 'address_verified_by', 'address_verified_at', 'notes', 'requested_by']),
                    'method' => DeliveryMethod::Shipping,
                    'status' => DeliveryStatus::Scheduled,
                    'rescheduled_from_id' => $shipment->id,
                ]);

                foreach ($remaining as $lineId => $quantity) {
                    $remainder->lines()->create(['customer_order_line_id' => $lineId, 'quantity' => $quantity]);
                }
            }

            if (! $this->deliveries()->active()->where('method', DeliveryMethod::Shipping)->where('status', DeliveryStatus::Scheduled)->exists()) {
                foreach ($this->shippingFeeLines() as $feeLine) {
                    $feeLine->update(['status' => CustomerOrderLineStatus::Completed, 'quantity_on_order' => 0, 'delivered_at' => now()]);
                }
            }

            $this->refreshStatusFromLines();

            return $remainder;
        });
    }

    /**
     * Ferme une livraison en route sans rien remettre au client (issue #93) : client absent, ou articles tous refusés.
     * Toutes les lignes chargées retournent en stock client, avec le statut qu'elles avaient avant le chargement, et
     * la livraison passe « Non livrée ».
     *
     * @param  array<int, string>  $refusalReasons  raison par ligne de livraison (tout refusé), sinon la raison générale
     * @param  array<int, int|null>  $returnLocations  emplacement où l'article est rangé au retour, par ligne de livraison
     * @return list<string> les articles revenus au magasin (« 1 × Modèle : raison »)
     *
     * @throws DomainException si la livraison n'est pas en route ou si un article refusé n'a pas de raison.
     */
    public function failDelivery(CustomerOrderDelivery $delivery, UndeliveredReason $reason, array $refusalReasons = [], array $returnLocations = []): array
    {
        return DB::transaction(function () use ($delivery, $reason, $refusalReasons, $returnLocations): array {
            $delivery = $this->lockedDelivery($delivery, DeliveryStatus::InTransit, __('Seule une livraison en route peut être fermée sans remise.'));
            $returned = [];

            foreach ($delivery->lines()->get() as $deliveryLine) {
                $lineReason = $reason === UndeliveredReason::AllRefused ? trim((string) ($refusalReasons[$deliveryLine->id] ?? '')) : $reason->label();

                if ($lineReason === '') {
                    throw new DomainException(__('Indiquez la raison de chaque article refusé.'));
                }

                $line = $this->lines()->lockForUpdate()->findOrFail($deliveryLine->customer_order_line_id);
                $this->returnFromDelivery($delivery, $deliveryLine, $line, $deliveryLine->quantity, $returnLocations[$deliveryLine->id] ?? null);
                $deliveryLine->update(['accepted_quantity' => 0, 'refusal_reason' => $lineReason]);
                $returned[] = __(':quantity × :label : :reason', ['quantity' => $deliveryLine->quantity, 'label' => $line->label, 'reason' => $lineReason]);
            }

            $delivery->update(['status' => DeliveryStatus::Undelivered, 'undelivered_reason' => $reason, 'undelivered_at' => now(), 'delivered_by' => auth()->id()]);

            $this->refreshStatusFromLines();

            return $returned;
        });
    }

    /**
     * Retourne en stock client une quantité chargée d'une ligne « En livraison », avec le statut qu'avait la ligne
     * avant le chargement, et note l'emplacement où le livreur la range (issue #96), s'il est donné.
     */
    private function returnFromDelivery(CustomerOrderDelivery $delivery, CustomerOrderDeliveryLine $deliveryLine, CustomerOrderLine $line, int $quantity, ?int $locationId = null): void
    {
        if (! $line->isPart()) {
            InventoryMovement::record($line->product_id, $this->stockStoreId(), InventoryStatus::InDelivery, InventoryStatus::ReservedCustomer, $quantity, InventoryMovementType::DeliveryRefused, $delivery);
        }

        $line->refresh()->update(['status' => $deliveryLine->loaded_from_status ?? CustomerOrderLineStatus::Received]);

        if ($locationId !== null) {
            $this->placeLine($line, $locationId);
        }
    }

    /**
     * Relit et verrouille une livraison par nos camions de cette commande, dans le statut attendu.
     *
     * @throws DomainException
     */
    private function lockedDelivery(CustomerOrderDelivery $delivery, DeliveryStatus $expected, string $message): CustomerOrderDelivery
    {
        $locked = CustomerOrderDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

        if ($locked->customer_order_id !== $this->id || $locked->method !== DeliveryMethod::Internal || $locked->status !== $expected) {
            throw new DomainException($message);
        }

        return $locked;
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
