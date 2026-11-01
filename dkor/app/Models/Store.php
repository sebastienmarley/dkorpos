<?php

namespace App\Models;

use App\Enums\Province;
use App\Enums\StoreType;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property StoreType $type
 * @property Province $province Province dont les taxes s'appliquent aux ventes du magasin.
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address_civic
 * @property string|null $address_apartment
 * @property string|null $address_street
 * @property string|null $address_city
 * @property string|null $address_province
 * @property string|null $address_country
 * @property string|null $address_postal_code
 * @property string|null $bank_account
 * @property string|null $vacation_accrual_start Jour de début de l'accumulation des vacances (MM-JJ).
 * @property string|null $sick_accrual_start Jour de début de l'accumulation des maladies (MM-JJ).
 * @property int|null $sick_days_full_time Maximum de jours de maladie payés, temps plein.
 * @property float $custom_deposit_percent Dépôt exigé (en %) sur les articles sur mesure non encore remis.
 * @property float|null $shipping_max_length Plus grande dimension (longueur, largeur ou hauteur) d'un article expédiable, dans l'unité des fiches produits.
 * @property float|null $shipping_max_weight Poids maximum d'un article expédiable, dans l'unité des fiches produits.
 * @property float $cancellation_fee_percent Frais d'annulation (en % du prix vendant) d'un article déjà commandé.
 * @property int|null $sick_days_part_time Maximum de jours de maladie payés, temps partiel.
 * @property array<string, array{open: bool, from: string, to: string}>|null $opening_hours
 * @property int|null $warehouse_store_id
 * @property int|null $shipping_warehouse_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'type', 'province', 'phone', 'email',
    'address_civic', 'address_apartment', 'address_street', 'address_city',
    'address_province', 'address_country', 'address_postal_code',
    'bank_account', 'cancellation_fee_percent', 'custom_deposit_percent', 'shipping_max_length', 'shipping_max_weight',
    'vacation_accrual_start', 'sick_accrual_start', 'sick_days_full_time', 'sick_days_part_time',
    'opening_hours', 'warehouse_store_id', 'shipping_warehouse_id', 'is_active',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected $attributes = [
        'is_active' => true,
        'custom_deposit_percent' => 50,
    ];

    protected $casts = [
        'type' => StoreType::class,
        'province' => Province::class,
        'opening_hours' => 'array',
        'cancellation_fee_percent' => 'float',
        'custom_deposit_percent' => 'float',
        'shipping_max_length' => 'float',
        'shipping_max_weight' => 'float',
        'sick_days_full_time' => 'integer',
        'sick_days_part_time' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Magasin par défaut d'un usager (ADR 0012) : le sien, sinon le premier magasin physique actif (bureau chef).
     */
    public static function defaultIdFor(?User $user): ?int
    {
        return $user->store_id ?? static::query()->where('type', StoreType::Physical)->orderBy('id')->value('id');
    }

    /**
     * Magasins pour lesquels l'usager peut agir (ADR 0013) : tous avec stores.all, sinon son magasin d'appartenance.
     *
     * @param  Builder<Store>  $query
     */
    #[Scope]
    protected function accessibleTo(Builder $query, User $user): void
    {
        if (! $user->hasAllStores()) {
            $query->whereKey(array_filter([$user->store_id]));
        }
    }

    /**
     * Magasin dont le stock sert les ventes de ce magasin (ADR 0012) : son entrepôt s'il en a un, sinon lui-même.
     */
    public function stockStoreId(): int
    {
        return $this->warehouse_store_id ?? $this->id;
    }

    /** @return HasMany<InventoryZone, $this> */
    public function inventoryZones(): HasMany
    {
        return $this->hasMany(InventoryZone::class);
    }

    /** @return HasMany<DeliveryTruck, $this> */
    public function deliveryTrucks(): HasMany
    {
        return $this->hasMany(DeliveryTruck::class);
    }

    /** La livraison par nos camions est offerte seulement si le magasin a au moins un camion actif. */
    public function offersDelivery(): bool
    {
        return $this->deliveryTrucks()->active()->exists();
    }

    /**
     * L'expédition est offerte seulement si le magasin a un entrepôt d'expédition : c'est lui qui fait les expéditions
     * de ses commandes.
     */
    public function offersShipping(): bool
    {
        return $this->shipping_warehouse_id !== null;
    }

    /** @return HasMany<StoreTaxRegistration, $this> */
    public function taxRegistrations(): HasMany
    {
        return $this->hasMany(StoreTaxRegistration::class);
    }

    /**
     * Noms des taxes en vigueur aujourd'hui dans la province du magasin (TPS, TVQ ou TVH…).
     *
     * @return Collection<int, string>
     */
    public function applicableTaxNames(): Collection
    {
        return Tax::query()
            ->activeOn(Carbon::today())
            ->where('province', $this->province)
            ->orderBy('start_date')
            ->orderBy('id')
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * Magasin physique qui sert d'entrepôt à cet emplacement.
     *
     * @return BelongsTo<Store, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'warehouse_store_id');
    }

    /**
     * Horaire par défaut : ouvert du lundi au vendredi.
     *
     * @return array<string, array{open: bool, from: string, to: string}>
     */
    public static function defaultOpeningHours(): array
    {
        return collect(self::DAYS)
            ->mapWithKeys(fn (string $day, int $index) => [
                $day => ['open' => $index < 5, 'from' => '09:00', 'to' => '17:00'],
            ])
            ->all();
    }
}
