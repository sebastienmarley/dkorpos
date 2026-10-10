<?php

namespace App\Models;

use App\Enums\Province;
use App\Enums\StoreType;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    'bank_account', 'cancellation_fee_percent', 'custom_deposit_percent',
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
        'sick_days_full_time' => 'integer',
        'sick_days_part_time' => 'integer',
        'is_active' => 'boolean',
    ];

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
     * Magasin physique d'où partent les expéditions de cet emplacement.
     *
     * @return BelongsTo<Store, $this>
     */
    public function shippingWarehouse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shipping_warehouse_id');
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
