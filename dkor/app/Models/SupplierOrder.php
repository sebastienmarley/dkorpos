<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use App\Enums\DefectiveResolution;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Events\SupplierOrderLineSubstituted;
use App\Mail\SupplierOrderLineCancellationRequested;
use App\Mail\SupplierOrderPlaced;
use Database\Factories\SupplierOrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * @property int $id
 * @property string|null $number
 * @property string|null $quote_number
 * @property SupplierType $type
 * @property int $supplier_id
 * @property SupplierOrderStatus $status
 * @property bool $is_collect
 * @property int|null $shipping_supplier_id
 * @property bool $is_drop_ship
 * @property string|null $drop_ship_name
 * @property string|null $drop_ship_address_civic
 * @property string|null $drop_ship_address_apartment
 * @property string|null $drop_ship_address_street
 * @property string|null $drop_ship_address_city
 * @property string|null $drop_ship_address_province
 * @property string|null $drop_ship_address_country
 * @property string|null $drop_ship_address_postal_code
 * @property string|null $notes
 * @property Carbon|null $sent_at
 * @property Carbon|null $last_emailed_at
 * @property Carbon|null $received_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Supplier|null $shippingSupplier
 * @property-read User|null $creator
 * @property-read Collection<int, SupplierOrderLine> $lines
 * @property-read float $total
 */
#[Fillable(['number', 'quote_number', 'type', 'supplier_id', 'status', 'is_collect', 'shipping_supplier_id', 'is_drop_ship', 'drop_ship_name', 'drop_ship_address_civic', 'drop_ship_address_apartment', 'drop_ship_address_street', 'drop_ship_address_city', 'drop_ship_address_province', 'drop_ship_address_country', 'drop_ship_address_postal_code', 'notes', 'sent_at', 'last_emailed_at', 'received_at', 'created_by'])]
class SupplierOrder extends Model
{
    /** @use HasFactory<SupplierOrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'is_collect' => false,
        'is_drop_ship' => false,
    ];

    protected $casts = [
        'type' => SupplierType::class,
        'status' => SupplierOrderStatus::class,
        'sent_at' => 'datetime',
        'last_emailed_at' => 'datetime',
        'received_at' => 'datetime',
        'is_collect' => 'boolean',
        'is_drop_ship' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierOrder $order): void {
            $supplier = Supplier::find($order->supplier_id);

            if ($supplier?->collect && $order->type === SupplierType::Product) {
                $order->is_collect = true;
                $order->shipping_supplier_id ??= $supplier->default_shipping_supplier_id;
            }

            $isDraft = ($order->status ?? SupplierOrderStatus::Draft) === SupplierOrderStatus::Draft;

            if ($isDraft && static::hasDraftFor($order->supplier_id)) {
                throw new DomainException(__('Une commande en brouillon existe déjà pour ce fournisseur.'));
            }
        });

        static::created(function (SupplierOrder $order): void {
            if ($order->number === null) {
                $order->number = 'CF-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function shippingSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'shipping_supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<SupplierInvoice, $this> */
    public function supplierInvoice(): HasOne
    {
        return $this->hasOne(SupplierInvoice::class);
    }

    /** @return HasMany<SupplierOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierOrderLine::class)->orderBy('id');
    }

    /**
     * Lignes de réceptions terminées de cette commande (celles qui ont touché l'inventaire).
     *
     * @return HasManyThrough<ReceptionLine, SupplierOrderLine, $this>
     */
    public function completedReceptionLines(): HasManyThrough
    {
        return $this->receptionLines()->whereHas('reception', fn ($query) => $query->where('status', ReceptionStatus::Completed));
    }

    /** @return HasManyThrough<ReceptionLine, SupplierOrderLine, $this> */
    public function receptionLines(): HasManyThrough
    {
        return $this->hasManyThrough(ReceptionLine::class, SupplierOrderLine::class, 'supplier_order_id', 'supplier_order_line_id');
    }

    public function getTotalAttribute(): float
    {
        return round($this->lines
            ->reject(fn (SupplierOrderLine $line) => $line->status->isClosed())
            ->sum(fn (SupplierOrderLine $line) => $line->total), 2);
    }

    /**
     * Un fournisseur n'a jamais plus d'une commande en brouillon à la fois.
     */
    public static function hasDraftFor(int|string|null $supplierId): bool
    {
        return static::query()
            ->where('supplier_id', $supplierId)
            ->where('status', SupplierOrderStatus::Draft)
            ->exists();
    }

    /**
     * Seule une commande non envoyée (brouillon ou en attente) sans aucune ligne peut être supprimée: une ligne peut
     * être associée à un client.
     */
    public function isDeletable(): bool
    {
        return $this->status->isEditable() && $this->lines()->doesntExist();
    }

    /**
     * Montant minimum du fournisseur pour que le transport soit prépayé (0 si aucun minimum).
     */
    public function prepaidThreshold(): float
    {
        return (float) $this->supplier->prepaid_amount;
    }

    /**
     * Un fournisseur « collect » expédie toujours collect; sinon la commande l'est par choix.
     */
    public function isCollectShipping(): bool
    {
        return $this->is_collect || (bool) $this->supplier->collect;
    }

    public function meetsPrepaidThreshold(): bool
    {
        return $this->total >= $this->prepaidThreshold();
    }

    public function missingForPrepaid(): float
    {
        return max(0, round($this->prepaidThreshold() - $this->total, 2));
    }

    /**
     * Choisit le transport: prépayé (montant minimum du fournisseur à atteindre) ou collect, ce qui exige un
     * fournisseur d'expédition. Un fournisseur « collect » impose le collect.
     */
    public function setShipping(bool $collect, ?int $shippingSupplierId = null): void
    {
        if (! $this->isProductOrder() || ! $this->status->isEditable()) {
            throw new DomainException(__('Le transport ne se modifie que sur une commande de produits non envoyée.'));
        }

        $collect = $collect || (bool) $this->supplier->collect;

        if ($collect && $shippingSupplierId === null) {
            throw new DomainException(__('Choisissez un fournisseur d\'expédition pour une commande collect.'));
        }

        if ($collect && ! Supplier::query()->whereKey($shippingSupplierId)->where('type', SupplierType::Shipping)->where('is_active', true)->exists()) {
            throw new DomainException(__('Ce fournisseur d\'expédition n\'est pas valide.'));
        }

        $this->update([
            'is_collect' => $collect,
            'shipping_supplier_id' => $collect ? $shippingSupplierId : null,
        ]);
    }

    /**
     * Active le drop ship (livraison à une adresse autre que celle du marchand) ou le désactive en effaçant l'adresse.
     *
     * @param  array{name?: string|null, civic?: string|null, apartment?: string|null, street?: string|null, city?: string|null, province?: string|null, country?: string|null, postal_code?: string|null}  $destination
     */
    public function setDropShip(bool $enabled, array $destination = []): void
    {
        if (! $this->isProductOrder() || ! $this->status->isEditable()) {
            throw new DomainException(__('Le drop ship ne se modifie que sur une commande de produits non envoyée.'));
        }

        if ($enabled && collect(['name', 'civic', 'street', 'city', 'province', 'country', 'postal_code'])->contains(fn (string $key) => blank($destination[$key] ?? null))) {
            throw new DomainException(__('Le nom et l\'adresse de livraison sont requis pour un drop ship.'));
        }

        $value = fn (string $key) => $enabled && filled($destination[$key] ?? null) ? $destination[$key] : null;

        $this->update([
            'is_drop_ship' => $enabled,
            'drop_ship_name' => $value('name'),
            'drop_ship_address_civic' => $value('civic'),
            'drop_ship_address_apartment' => $value('apartment'),
            'drop_ship_address_street' => $value('street'),
            'drop_ship_address_city' => $value('city'),
            'drop_ship_address_province' => $value('province'),
            'drop_ship_address_country' => $value('country'),
            'drop_ship_address_postal_code' => $value('postal_code'),
        ]);
    }

    /**
     * Adresse de livraison drop ship sur deux lignes (vide si la commande n'est pas en drop ship).
     */
    public function dropShipAddressLabel(): string
    {
        if (! $this->is_drop_ship) {
            return '';
        }

        $street = trim($this->drop_ship_address_civic.' '.$this->drop_ship_address_street);

        if (filled($this->drop_ship_address_apartment)) {
            $street .= ', '.__('app.').' '.$this->drop_ship_address_apartment;
        }

        return $street."\n".trim($this->drop_ship_address_city.' '.$this->drop_ship_address_province.' '.$this->drop_ship_address_postal_code.' '.$this->drop_ship_address_country);
    }

    public function isProductOrder(): bool
    {
        return $this->type === SupplierType::Product;
    }

    /**
     * Met le brouillon en attente d'envoi: il libère le brouillon du fournisseur pour une nouvelle commande.
     */
    public function markPending(): void
    {
        $this->guardStatus(SupplierOrderStatus::Draft, __('Seule une commande en brouillon peut être mise en attente.'));

        if ($this->lines()->doesntExist()) {
            throw new DomainException(__('Ajoutez au moins une ligne avant de mettre la commande en attente.'));
        }

        $this->update(['status' => SupplierOrderStatus::Pending]);
    }

    /**
     * Envoie la commande (en brouillon ou en attente); les quantités des produits passent « en commande » dans l'inventaire, les
     * lignes de commandes clients liées passent à « Commandé » et la commande est transmise par courriel au fournisseur.
     *
     * @return bool Faux seulement si le courriel est activé mais n'a pas pu être envoyé (pas de courriel ou erreur d'envoi).
     */
    public function send(): bool
    {
        if (! $this->status->isEditable()) {
            throw new DomainException(__('Seule une commande en brouillon ou en attente peut être envoyée.'));
        }

        DB::transaction(function (): void {
            $lines = $this->lines()->get();

            if ($lines->isEmpty()) {
                throw new DomainException(__('Ajoutez au moins une ligne avant d\'envoyer la commande.'));
            }

            if ($this->isProductOrder()) {
                $this->guardShipping();

                foreach ($lines as $line) {
                    $this->moveStock($line, null, InventoryStatus::OnOrder, $line->quantity, InventoryMovementType::OrderPlaced);
                }
            }

            CustomerOrderLine::query()
                ->whereIn('supplier_order_line_id', $lines->pluck('id'))
                ->where('status', CustomerOrderLineStatus::OnOrder)
                ->update(['status' => CustomerOrderLineStatus::Ordered]);

            $this->update(['status' => SupplierOrderStatus::Sent, 'sent_at' => now()]);
        });

        return ! static::emailEnabled() || $this->emailToSupplier();
    }

    /**
     * Les courriels aux fournisseurs sont-ils activés (config supplier_orders.email_enabled)?
     */
    public static function emailEnabled(): bool
    {
        return (bool) config('supplier_orders.email_enabled');
    }

    /**
     * Transmet la commande au courriel de commande du fournisseur (ou à son courriel général); peut être relancé.
     */
    public function emailToSupplier(): bool
    {
        $recipient = $this->supplier->order_email ?: $this->supplier->email;

        if (blank($recipient)) {
            return false;
        }

        try {
            Mail::to($recipient)->send(new SupplierOrderPlaced($this->load(['supplier', 'shippingSupplier', 'lines.product'])));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $this->update(['last_emailed_at' => now()]);

        return true;
    }

    /**
     * Réceptionne des produits de cette commande (voir Reception::record pour les règles).
     *
     * @param  array<int, array{quantity: int|string}>  $receipts  Indexé par id de ligne.
     */
    public function receive(array $receipts, int|string|null $receivedBy = null): Reception
    {
        if (! $this->isProductOrder() || ! $this->status->isOpen()) {
            throw new DomainException(__('Cette commande ne peut pas être réceptionnée.'));
        }

        return Reception::record($this->supplier, $receipts, ['received_by' => $receivedBy]);
    }

    /**
     * Applique à l'inventaire (unités FIFO au coût de la commande, stock, « en commande ») les lignes d'une réception
     * en cours qui concernent cette commande. Les quantités sont replafonnées à ce qui reste à recevoir; une ligne
     * qui n'a plus rien à recevoir est retirée de la réception. La partie endommagée compte comme reçue mais entre en
     * inventaire défectueux (sans unité vendable) avec un dossier défectueux; pour un client lié, elle est recommandée.
     * Appelé par Reception::complete dans sa transaction.
     *
     * @param  Collection<int, ReceptionLine>  $receptionLines
     */
    public function applyReceiptLines(Reception $reception, Collection $receptionLines): void
    {
        foreach ($receptionLines as $receptionLine) {
            $line = $receptionLine->orderLine()->firstOrFail();
            $quantity = min($receptionLine->quantity, $line->quantity_outstanding);

            if ($quantity <= 0) {
                $receptionLine->delete();

                continue;
            }

            $cost = round($line->unit_cost, 2);
            $damaged = $line->product_id === null ? 0 : min($receptionLine->quantity_damaged, $quantity);
            $good = $quantity - $damaged;

            $receptionLine->update(['quantity' => $quantity, 'quantity_damaged' => $damaged, 'unit_cost' => $cost]);

            for ($i = 0; $line->product_id !== null && $i < $good; $i++) {
                InventoryUnit::create([
                    'product_id' => $line->product_id,
                    'reception_line_id' => $receptionLine->id,
                    'cost' => $cost,
                    'inserted_at' => today(),
                ]);
            }

            $this->moveStock($line, InventoryStatus::OnOrder, InventoryStatus::InStock, $good, InventoryMovementType::Receipt, $receptionLine);
            $this->moveStock($line, InventoryStatus::OnOrder, InventoryStatus::DefectiveStock, $damaged, InventoryMovementType::ReceiptDamaged, $receptionLine);

            $line->update(['quantity_received' => $line->quantity_received + $quantity]);

            if ($damaged > 0) {
                DefectiveProduct::create([
                    'product_id' => $line->product_id,
                    'quantity' => $damaged,
                    'resolution' => DefectiveResolution::DamagedOnArrival,
                    'reason' => __('Endommagé à l\'arrivée'),
                    'supplier_order_line_id' => $line->id,
                    'reception_line_id' => $receptionLine->id,
                    'created_by' => $reception->received_by ?? auth()->id(),
                ]);
            }

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierReceipt($customerLine, $good);

            if ($damaged > 0) {
                $customerLine = $line->customerOrderLine()->with('order')->first();
                $customerLine?->order->applySupplierDamagedReceipt($customerLine, $damaged);
            }
        }

        $this->refreshStatusFromLines();
    }

    /**
     * Renverse une quantité d'une réception faite par erreur, tant que la commande n'est pas facturée: les unités
     * d'inventaire créées sont retirées (elles ne doivent pas être déjà livrées), le stock en main diminue, la
     * quantité revient « en commande », la ligne de commande retrouve ce qu'il lui reste à recevoir et le statut de la
     * commande est recalculé (reçue → partiellement reçue ou envoyée).
     */
    public function reverseReceipt(ReceptionLine $receptionLine, int $quantity, ?string $reason = null, int|string|null $reversedBy = null): void
    {
        $line = $receptionLine->orderLine;

        if ($line->supplier_order_id !== $this->id) {
            throw new DomainException(__('Cette réception ne concerne pas cette commande.'));
        }

        if ($receptionLine->reception->invoice()->exists()) {
            throw new DomainException(__('Cette réception est déjà facturée : elle ne peut plus être renversée.'));
        }

        if ($receptionLine->reception->isInProgress()) {
            throw new DomainException(__('Cette réception est en cours : il n\'y a rien à renverser.'));
        }

        if (! in_array($this->status, [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived, SupplierOrderStatus::Received], true)) {
            throw new DomainException(__('Une réception ne peut plus être renversée une fois la commande facturée ou annulée.'));
        }

        if ($quantity < 1 || $quantity > $receptionLine->quantity_net) {
            throw new DomainException(__('La quantité à renverser doit être entre 1 et :max.', ['max' => $receptionLine->quantity_net]));
        }

        DB::transaction(function () use ($receptionLine, $line, $quantity, $reason, $reversedBy): void {
            $units = InventoryUnit::query()
                ->where('reception_line_id', $receptionLine->id)
                ->whereNull('delivered_at')
                ->orderByDesc('id')
                ->limit($quantity)
                ->get();

            if ($units->count() < $quantity) {
                throw new DomainException(__('Impossible de renverser : certaines unités reçues ont déjà été livrées ou ne sont pas retraçables.'));
            }

            InventoryUnit::whereKey($units->modelKeys())->delete();

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierReceiptReversal($customerLine, $quantity);

            $this->moveStock($line, InventoryStatus::InStock, InventoryStatus::OnOrder, $quantity, InventoryMovementType::ReceiptReversal, $receptionLine, $reason);

            $line->update(['quantity_received' => $line->quantity_received - $quantity]);

            $receptionLine->update([
                'quantity_reversed' => $receptionLine->quantity_reversed + $quantity,
                'reversed_at' => now(),
                'reversed_by' => $reversedBy,
                'reversal_reason' => filled($reason) ? $reason : null,
            ]);

            $this->refreshStatusFromLines();
        });
    }

    /**
     * Ajoute une ligne en brouillon ou après l'envoi. Après l'envoi, la quantité d'un produit passe
     * immédiatement « en commande » dans l'inventaire.
     *
     * @param  array{product_id?: int|string|null, description?: string|null, quantity: int|string, unit_cost: float|int|string}  $attributes
     */
    public function addLine(array $attributes): SupplierOrderLine
    {
        if (! ($this->status->isEditable() || $this->status->isOpen())) {
            throw new DomainException(__('On ne peut plus ajouter de ligne à cette commande.'));
        }

        return DB::transaction(function () use ($attributes): SupplierOrderLine {
            $line = $this->lines()->create($attributes);

            if ($this->isProductOrder() && $this->status->isOpen()) {
                $this->moveStock($line, null, InventoryStatus::OnOrder, $line->quantity, InventoryMovementType::OrderLineAdded);
            }

            return $line;
        });
    }

    /**
     * Modifie la quantité et le coût d'une ligne, en brouillon ou après l'envoi. Après l'envoi, la quantité ne peut
     * pas descendre sous ce qui est déjà reçu et l'inventaire « en commande » suit la différence. La quantité « en
     * commande » de la ligne de commande client liée suit la nouvelle quantité.
     */
    public function updateLine(SupplierOrderLine $line, int $quantity, float $unitCost): void
    {
        if (! ($this->status->isEditable() || $this->status->isOpen()) || $line->supplier_order_id !== $this->id) {
            throw new DomainException(__('Les lignes de cette commande ne sont plus modifiables.'));
        }

        if ($line->status !== SupplierOrderLineStatus::Active) {
            throw new DomainException(__('Seule une ligne active peut être modifiée.'));
        }

        if ($quantity < max(1, $line->quantity_received)) {
            throw new DomainException(__('La quantité ne peut pas être inférieure à la quantité déjà reçue.'));
        }

        DB::transaction(function () use ($line, $quantity, $unitCost): void {
            if ($this->isProductOrder() && $this->status->isOpen()) {
                $this->moveStock(
                    $line,
                    $quantity > $line->quantity ? null : InventoryStatus::OnOrder,
                    $quantity > $line->quantity ? InventoryStatus::OnOrder : null,
                    abs($quantity - $line->quantity),
                    InventoryMovementType::OrderLineChanged,
                );
            }

            $line->update(['quantity' => $quantity, 'unit_cost' => round($unitCost, 2)]);

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierQuantity($customerLine, $quantity - $line->quantity_received);

            if ($this->status->isOpen()) {
                $this->refreshStatusFromLines();
            }
        });
    }

    /**
     * Substitue le produit d'une ligne par un autre du même fournisseur. Ce qui reste à recevoir passe sur une
     * nouvelle ligne (coût du nouveau produit) liée à l'originale; l'originale est « substituée » avec une quantité
     * de 0 (ou réduite à ce qui est déjà reçu). L'inventaire « en commande » suit.
     */
    public function substituteLine(SupplierOrderLine $line, Product $product): SupplierOrderLine
    {
        $this->guardLineBelongs($line);

        if (! $this->isProductOrder() || ! $this->status->isOpen()) {
            throw new DomainException(__('Une substitution se fait sur une commande de produits envoyée et non reçue.'));
        }

        if ($line->status !== SupplierOrderLineStatus::Active || $line->quantity_outstanding === 0) {
            throw new DomainException(__('Cette ligne ne peut pas être substituée.'));
        }

        if ($product->supplier_id !== $this->supplier_id || $product->is_discontinued || $product->is_non_orderable || $product->id === $line->product_id) {
            throw new DomainException(__('Ce produit ne peut pas remplacer celui de la ligne.'));
        }

        $replacement = DB::transaction(function () use ($line, $product): SupplierOrderLine {
            $quantity = $line->quantity_outstanding;

            $this->moveStock($line, InventoryStatus::OnOrder, null, $quantity, InventoryMovementType::Substitution);

            $replacement = $this->lines()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $product->cost,
                'substituted_from_line_id' => $line->id,
            ]);

            $this->moveStock($replacement, null, InventoryStatus::OnOrder, $quantity, InventoryMovementType::Substitution);

            $line->update($line->quantity_received > 0
                ? ['quantity' => $line->quantity_received]
                : ['quantity' => 0, 'status' => SupplierOrderLineStatus::Substituted]);

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierSubstitution($customerLine, $line->quantity_received, $replacement);

            $this->refreshStatusFromLines();

            return $replacement;
        });

        SupplierOrderLineSubstituted::dispatch($line, $replacement);

        return $replacement;
    }

    /**
     * Demande au fournisseur d'annuler ce qui reste à recevoir d'une ligne; la ligne (et la ligne de commande client
     * liée) passe « en demande d'annulation » jusqu'à la réponse du fournisseur.
     *
     * @return bool Vrai si le courriel a été envoyé (faux s'il est désactivé ou si le fournisseur n'a aucun courriel).
     */
    public function requestLineCancellation(SupplierOrderLine $line, ?string $reason = null): bool
    {
        $this->guardLineBelongs($line);

        if (! $this->status->isOpen()) {
            throw new DomainException(__('Une demande d\'annulation se fait sur une commande envoyée et non reçue.'));
        }

        if ($line->status !== SupplierOrderLineStatus::Active || $line->quantity_outstanding === 0) {
            throw new DomainException(__('Cette ligne ne peut pas faire l\'objet d\'une demande d\'annulation.'));
        }

        DB::transaction(function () use ($line, $reason): void {
            $line->update([
                'status' => SupplierOrderLineStatus::CancellationRequested,
                'cancellation_reason' => filled($reason) ? $reason : null,
                'cancellation_requested_at' => now(),
            ]);

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierCancellationRequest($customerLine);
        });

        $recipient = $this->supplier->order_email ?: $this->supplier->email;

        if (! static::emailEnabled() || blank($recipient)) {
            return false;
        }

        Mail::to($recipient)->send(new SupplierOrderLineCancellationRequested($line->load('order.supplier', 'product')));

        return true;
    }

    /**
     * Le fournisseur confirme l'annulation. Ce qui est déjà reçu est conservé (la quantité de la ligne est réduite à
     * la quantité reçue); sans réception, la ligne est annulée. La ligne de commande client liée suit.
     */
    public function confirmLineCancellation(SupplierOrderLine $line): void
    {
        $this->guardLineBelongs($line);
        $this->guardCancellationRequested($line);

        DB::transaction(function () use ($line): void {
            if ($this->isProductOrder()) {
                $this->moveStock($line, InventoryStatus::OnOrder, null, $line->quantity_outstanding, InventoryMovementType::OrderLineCancelled);
            }

            if ($line->quantity_received > 0) {
                $line->update([
                    'quantity' => $line->quantity_received,
                    'status' => SupplierOrderLineStatus::Active,
                    'cancellation_requested_at' => null,
                ]);
            } else {
                $line->update(['status' => SupplierOrderLineStatus::Cancelled, 'cancelled_at' => now()]);
            }

            $this->cancelCustomerQuantity($line);

            $this->refreshStatusFromLines();
        });
    }

    /**
     * Le fournisseur refuse l'annulation: la ligne redevient active et la ligne de commande client liée « Commandé ».
     */
    public function rejectLineCancellation(SupplierOrderLine $line): void
    {
        $this->guardLineBelongs($line);
        $this->guardCancellationRequested($line);

        DB::transaction(function () use ($line): void {
            $line->update([
                'status' => SupplierOrderLineStatus::Active,
                'cancellation_reason' => null,
                'cancellation_requested_at' => null,
            ]);

            $customerLine = $line->customerOrderLine()->with('order')->first();
            $customerLine?->order->applySupplierCancellationRejected($customerLine);
        });
    }

    /**
     * Marque une commande de services comme complétée.
     */
    public function complete(): void
    {
        if ($this->isProductOrder() || $this->status !== SupplierOrderStatus::Sent) {
            throw new DomainException(__('Cette commande ne peut pas être complétée.'));
        }

        $this->update(['status' => SupplierOrderStatus::Received, 'received_at' => now()]);
    }

    /**
     * Passe la commande à « Facturée » quand elle est entièrement reçue et que toutes ses réceptions sont facturées.
     */
    public function markInvoicedIfSettled(): void
    {
        if ($this->status !== SupplierOrderStatus::Received || ! $this->isProductOrder()) {
            return;
        }

        $unbilled = $this->completedReceptionLines()
            ->whereColumn('reception_lines.quantity', '>', 'reception_lines.quantity_reversed')
            ->whereDoesntHave('reception.invoice')
            ->exists();

        if (! $unbilled) {
            $this->update(['status' => SupplierOrderStatus::Invoiced]);
        }
    }

    /**
     * Annule la commande; les quantités encore en attente sont retirées de l'inventaire « en commande » et des lignes
     * de commandes clients liées.
     */
    public function cancel(): void
    {
        if (! ($this->status->isEditable() || $this->status->isOpen())) {
            throw new DomainException(__('Cette commande ne peut plus être annulée.'));
        }

        DB::transaction(function (): void {
            if ($this->isProductOrder() && $this->status->isOpen()) {
                foreach ($this->lines()->get() as $line) {
                    $this->moveStock($line, InventoryStatus::OnOrder, null, $line->quantity_outstanding, InventoryMovementType::OrderCancelled);
                }
            }

            foreach ($this->lines()->get()->reject(fn (SupplierOrderLine $line) => $line->status->isClosed()) as $line) {
                $this->cancelCustomerQuantity($line);
            }

            $this->update(['status' => SupplierOrderStatus::Cancelled]);
        });
    }

    /**
     * Recalcule le statut d'une commande ouverte d'après ses lignes (les lignes annulées ne comptent plus).
     */
    private function refreshStatusFromLines(): void
    {
        $lines = $this->lines()->get()->reject(fn (SupplierOrderLine $line) => $line->status->isClosed());

        if ($lines->isEmpty()) {
            $this->update(['status' => SupplierOrderStatus::Cancelled]);

            return;
        }

        if (! $this->isProductOrder()) {
            return;
        }

        if ($lines->every(fn (SupplierOrderLine $line) => $line->quantity_outstanding === 0)) {
            $this->update(['status' => SupplierOrderStatus::Received, 'received_at' => now()]);
        } else {
            $this->update([
                'received_at' => null,
                'status' => $lines->contains(fn (SupplierOrderLine $line) => $line->quantity_received > 0)
                    ? SupplierOrderStatus::PartiallyReceived
                    : SupplierOrderStatus::Sent,
            ]);
        }
    }

    private function guardShipping(): void
    {
        if ($this->isCollectShipping()) {
            if ($this->shipping_supplier_id === null) {
                throw new DomainException(__('Choisissez un fournisseur d\'expédition pour envoyer la commande collect.'));
            }

            return;
        }

        if (! $this->meetsPrepaidThreshold()) {
            throw new DomainException(__('Montant minimum prépayé non atteint (:missing $ manquants) : ajoutez des produits ou envoyez la commande collect.', [
                'missing' => number_format($this->missingForPrepaid(), 2),
            ]));
        }
    }

    /**
     * Retire de la ligne de commande client liée ce qui ne sera plus reçu (le déjà reçu reste réservé au client).
     */
    private function cancelCustomerQuantity(SupplierOrderLine $line): void
    {
        $customerLine = $line->customerOrderLine()->with('order')->first();
        $customerLine?->order->applySupplierCancellation($customerLine, $line->quantity_received);
    }

    private function guardLineBelongs(SupplierOrderLine $line): void
    {
        if ($line->supplier_order_id !== $this->id) {
            throw new DomainException(__('Cette ligne n\'appartient pas à la commande.'));
        }
    }

    private function guardCancellationRequested(SupplierOrderLine $line): void
    {
        if ($line->status !== SupplierOrderLineStatus::CancellationRequested) {
            throw new DomainException(__('Cette ligne n\'est pas en demande d\'annulation.'));
        }
    }

    /**
     * Consigne un mouvement d'inventaire pour le produit d'une ligne (rien pour une ligne libre ou une quantité nulle).
     */
    private function moveStock(
        SupplierOrderLine $line,
        ?InventoryStatus $from,
        ?InventoryStatus $to,
        int $quantity,
        InventoryMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
    ): void {
        if ($line->product_id === null || $quantity < 1) {
            return;
        }

        InventoryMovement::record($line->product_id, $from, $to, $quantity, $type, $reference ?? $line, $note);
    }

    private function guardStatus(SupplierOrderStatus $expected, string $message): void
    {
        if ($this->status !== $expected) {
            throw new DomainException($message);
        }
    }
}
