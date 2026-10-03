<?php

namespace App\Models;

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
 * @property string|null $invoice_number
 * @property Carbon|null $invoice_date
 * @property float|null $invoice_total
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Supplier|null $shippingSupplier
 * @property-read User|null $creator
 * @property-read Collection<int, SupplierOrderLine> $lines
 * @property-read float $total
 */
#[Fillable(['number', 'quote_number', 'type', 'supplier_id', 'status', 'is_collect', 'shipping_supplier_id', 'is_drop_ship', 'drop_ship_name', 'drop_ship_address_civic', 'drop_ship_address_apartment', 'drop_ship_address_street', 'drop_ship_address_city', 'drop_ship_address_province', 'drop_ship_address_country', 'drop_ship_address_postal_code', 'notes', 'sent_at', 'last_emailed_at', 'received_at', 'invoice_number', 'invoice_date', 'invoice_total', 'created_by'])]
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
        'invoice_date' => 'date',
        'invoice_total' => 'float',
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

    /** @return HasMany<SupplierOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierOrderLine::class)->orderBy('id');
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
     * Envoie la commande en attente; les quantités des produits passent « en commande » dans l'inventaire et la
     * commande est transmise par courriel au fournisseur.
     *
     * @return bool Vrai si le courriel a été envoyé (faux si le fournisseur n'a pas de courriel ou si l'envoi a échoué).
     */
    public function send(): bool
    {
        $this->guardStatus(SupplierOrderStatus::Pending, __('Seule une commande en attente peut être envoyée.'));

        DB::transaction(function (): void {
            $lines = $this->lines()->get();

            if ($lines->isEmpty()) {
                throw new DomainException(__('Ajoutez au moins une ligne avant d\'envoyer la commande.'));
            }

            if ($this->isProductOrder()) {
                $this->guardShipping();

                foreach ($lines as $line) {
                    $this->adjustStock($line, onOrder: $line->quantity);
                }
            }

            $this->update(['status' => SupplierOrderStatus::Sent, 'sent_at' => now()]);
        });

        return $this->emailToSupplier();
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
     * Réceptionne des produits (inventaire FIFO). Le coût réel saisi remplace le coût commandé.
     *
     * @param  array<int, array{quantity: int|string, unit_cost: float|int|string}>  $receipts  Indexé par id de ligne.
     */
    public function receive(array $receipts): void
    {
        if (! $this->isProductOrder() || ! $this->status->isOpen()) {
            throw new DomainException(__('Cette commande ne peut pas être réceptionnée.'));
        }

        DB::transaction(function () use ($receipts): void {
            $received = 0;

            foreach ($this->lines()->get() as $line) {
                $quantity = min((int) ($receipts[$line->id]['quantity'] ?? 0), $line->quantity_outstanding);

                if ($quantity <= 0) {
                    continue;
                }

                $cost = round((float) ($receipts[$line->id]['unit_cost'] ?? $line->unit_cost), 2);
                $received += $quantity;

                for ($i = 0; $i < $quantity; $i++) {
                    InventoryUnit::create([
                        'product_id' => $line->product_id,
                        'cost' => $cost,
                        'inserted_at' => today(),
                    ]);
                }

                $this->adjustStock($line, inStock: $quantity, onOrder: -$quantity);

                $line->update([
                    'quantity_received' => $line->quantity_received + $quantity,
                    'unit_cost' => $cost,
                ]);
            }

            if ($received === 0) {
                throw new DomainException(__('Saisissez au moins une quantité à réceptionner.'));
            }

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
                $this->adjustStock($line, onOrder: $line->quantity);
            }

            return $line;
        });
    }

    /**
     * Modifie la quantité et le coût d'une ligne, en brouillon ou après l'envoi. Après l'envoi, la quantité ne peut
     * pas descendre sous ce qui est déjà reçu et l'inventaire « en commande » suit la différence.
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
                $this->adjustStock($line, onOrder: $quantity - $line->quantity);
            }

            $line->update(['quantity' => $quantity, 'unit_cost' => round($unitCost, 2)]);

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

            $this->adjustStock($line, onOrder: -$quantity);

            $replacement = $this->lines()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $product->cost,
                'substituted_from_line_id' => $line->id,
            ]);

            $this->adjustStock($replacement, onOrder: $quantity);

            $line->update($line->quantity_received > 0
                ? ['quantity' => $line->quantity_received]
                : ['quantity' => 0, 'status' => SupplierOrderLineStatus::Substituted]);

            $this->refreshStatusFromLines();

            return $replacement;
        });

        SupplierOrderLineSubstituted::dispatch($line, $replacement);

        return $replacement;
    }

    /**
     * Demande au fournisseur d'annuler ce qui reste à recevoir d'une ligne; la ligne passe « en demande d'annulation »
     * jusqu'à la réponse du fournisseur.
     *
     * @return bool Vrai si le courriel a été envoyé (faux si le fournisseur n'a aucun courriel).
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

        $line->update([
            'status' => SupplierOrderLineStatus::CancellationRequested,
            'cancellation_reason' => filled($reason) ? $reason : null,
            'cancellation_requested_at' => now(),
        ]);

        $recipient = $this->supplier->order_email ?: $this->supplier->email;

        if (blank($recipient)) {
            return false;
        }

        Mail::to($recipient)->send(new SupplierOrderLineCancellationRequested($line->load('order.supplier', 'product')));

        return true;
    }

    /**
     * Le fournisseur confirme l'annulation. Ce qui est déjà reçu est conservé (la quantité de la ligne est réduite à
     * la quantité reçue); sans réception, la ligne est annulée.
     */
    public function confirmLineCancellation(SupplierOrderLine $line): void
    {
        $this->guardLineBelongs($line);
        $this->guardCancellationRequested($line);

        DB::transaction(function () use ($line): void {
            if ($this->isProductOrder()) {
                $this->adjustStock($line, onOrder: -$line->quantity_outstanding);
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

            $this->refreshStatusFromLines();
        });
    }

    /**
     * Le fournisseur refuse l'annulation: la ligne redevient active.
     */
    public function rejectLineCancellation(SupplierOrderLine $line): void
    {
        $this->guardLineBelongs($line);
        $this->guardCancellationRequested($line);

        $line->update([
            'status' => SupplierOrderLineStatus::Active,
            'cancellation_reason' => null,
            'cancellation_requested_at' => null,
        ]);
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
     * Enregistre la facture du fournisseur sur une commande reçue.
     */
    public function invoice(string $number, string $date, float $total): void
    {
        $this->guardStatus(SupplierOrderStatus::Received, __('La facture se saisit une fois la commande reçue.'));

        $this->update([
            'status' => SupplierOrderStatus::Invoiced,
            'invoice_number' => $number,
            'invoice_date' => $date,
            'invoice_total' => round($total, 2),
        ]);
    }

    /**
     * Annule la commande; les quantités encore en attente sont retirées de l'inventaire « en commande ».
     */
    public function cancel(): void
    {
        if (! ($this->status->isEditable() || $this->status->isOpen())) {
            throw new DomainException(__('Cette commande ne peut plus être annulée.'));
        }

        DB::transaction(function (): void {
            if ($this->isProductOrder() && $this->status->isOpen()) {
                foreach ($this->lines()->get() as $line) {
                    $this->adjustStock($line, onOrder: -$line->quantity_outstanding);
                }
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
            $this->update(['status' => $lines->contains(fn (SupplierOrderLine $line) => $line->quantity_received > 0)
                ? SupplierOrderStatus::PartiallyReceived
                : SupplierOrderStatus::Sent]);
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

    private function adjustStock(SupplierOrderLine $line, int $inStock = 0, int $onOrder = 0): void
    {
        if ($line->product_id === null) {
            return;
        }

        $stock = InventoryStock::firstOrCreate(['product_id' => $line->product_id]);

        $stock->update([
            'quantity_in_stock' => max(0, $stock->quantity_in_stock + $inStock),
            'quantity_on_order' => max(0, $stock->quantity_on_order + $onOrder),
        ]);
    }

    private function guardStatus(SupplierOrderStatus $expected, string $message): void
    {
        if ($this->status !== $expected) {
            throw new DomainException($message);
        }
    }
}
