<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\UndeliveredReason;
use Database\Factories\CustomerOrderDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Livraison d'articles d'une commande client, par nos camions (date et demi-journée) ou par expédition. L'adresse
 * vérifiée avec le client est copiée ici : la corriger plus tard dans sa fiche ne change pas une livraison prévue.
 *
 * @property int $id
 * @property int $customer_order_id
 * @property int|null $store_id Magasin dont les camions font la livraison.
 * @property int|null $rescheduled_from_id Livraison annulée que celle-ci remplace (report).
 * @property DeliveryMethod $method
 * @property DeliveryStatus $status
 * @property Carbon|null $scheduled_date
 * @property DeliveryPeriod|null $period
 * @property string $address_civic
 * @property string|null $address_apartment
 * @property string $address_street
 * @property string $address_city
 * @property string $address_province
 * @property string $address_country
 * @property string $address_postal_code
 * @property int|null $address_verified_by
 * @property Carbon $address_verified_at
 * @property bool $pay_on_delivery Le solde exigé sera perçu à la livraison (autorisé par un directeur).
 * @property int|null $pay_on_delivery_authorized_by
 * @property string|null $notes
 * @property Carbon|null $ready_at Moment où tous les articles sont en main (avis de nouvelle expédition envoyé).
 * @property int|null $route_position Rang dans la route du livreur, pour la demi-journée (null : ordre de planification).
 * @property Carbon|null $departed_at Départ du camion avec les articles chargés.
 * @property int|null $departed_by
 * @property Carbon|null $delivered_at Remise au client (articles acceptés).
 * @property int|null $delivered_by
 * @property string|null $signature_path Image de la signature du client (disque privé), supprimée après la durée de conservation.
 * @property string|null $signed_by_name
 * @property Carbon|null $signed_at
 * @property UndeliveredReason|null $undelivered_reason
 * @property Carbon|null $undelivered_at
 * @property int|null $requested_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 * @property-read Store|null $store
 * @property-read CustomerOrderDelivery|null $rescheduledFrom
 * @property-read Collection<int, CustomerOrderDeliveryLine> $lines
 * @property-read User|null $requester
 */
#[Fillable([
    'customer_order_id', 'store_id', 'rescheduled_from_id', 'method', 'status', 'scheduled_date', 'period',
    'address_civic', 'address_apartment', 'address_street', 'address_city', 'address_province', 'address_country',
    'address_postal_code', 'address_verified_by', 'address_verified_at', 'pay_on_delivery',
    'pay_on_delivery_authorized_by', 'notes', 'ready_at', 'route_position', 'departed_at', 'departed_by', 'delivered_at', 'delivered_by', 'signature_path', 'signed_by_name', 'signed_at', 'undelivered_reason', 'undelivered_at', 'requested_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class CustomerOrderDelivery extends Model
{
    /** @use HasFactory<CustomerOrderDeliveryFactory> */
    use HasFactory;

    /** Colonnes d'adresse copiées de la fiche du client. */
    public const array ADDRESS_FIELDS = [
        'address_civic', 'address_apartment', 'address_street', 'address_city',
        'address_province', 'address_country', 'address_postal_code',
    ];

    protected $attributes = [
        'pay_on_delivery' => false,
    ];

    protected $casts = [
        'method' => DeliveryMethod::class,
        'status' => DeliveryStatus::class,
        'scheduled_date' => 'date',
        'period' => DeliveryPeriod::class,
        'address_verified_at' => 'datetime',
        'pay_on_delivery' => 'boolean',
        'cancelled_at' => 'datetime',
        'ready_at' => 'datetime',
        'route_position' => 'integer',
        'departed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
        'undelivered_reason' => UndeliveredReason::class,
        'undelivered_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<CustomerOrderDelivery, $this> */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    /** @return HasMany<CustomerOrderDeliveryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderDeliveryLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Livraisons planifiées ou en route.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', DeliveryStatus::activeValues());
    }

    /** Adresse sur une ligne, pour l'affichage (ex. : 12, app. 3, rue Principale, Rimouski QC G5L 1A1). */
    public function formattedAddress(): string
    {
        $civic = $this->address_apartment ? $this->address_civic.'-'.$this->address_apartment : $this->address_civic;

        return trim($civic.' '.$this->address_street).', '.$this->address_city.' '.$this->address_province.' '.$this->address_postal_code;
    }

    /** Tous les articles prévus sont en main : la quantité réservée au client couvre chaque ligne. */
    public function hasAllItemsInHand(): bool
    {
        return $this->lines()->with('line')->get()->every(
            fn (CustomerOrderDeliveryLine $deliveryLine): bool => $deliveryLine->line->status->isPickable() && $deliveryLine->line->quantity_reserved >= $deliveryLine->quantity,
        );
    }

    /** Statut affiché selon la méthode : une expédition planifiée est « À expédier ». */
    public function statusLabel(): string
    {
        return $this->status->labelFor($this->method);
    }

    /** Titre de la livraison : sa date et sa demi-journée, ou « Expédition ». */
    public function title(): string
    {
        return $this->method === DeliveryMethod::Shipping ? $this->method->label() : $this->slotLabel();
    }

    /** Date et demi-journée planifiées (ex. : mar 21 oct 2026 AM). */
    public function slotLabel(): string
    {
        if ($this->scheduled_date === null) {
            return '';
        }

        return $this->scheduled_date->translatedFormat('D j M Y').($this->period ? ' '.$this->period->label() : '');
    }
}
