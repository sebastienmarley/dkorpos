<?php

namespace App\Models;

use App\Enums\SupplierType;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SupplierType $type
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address_civic
 * @property string|null $address_apartment
 * @property string|null $address_street
 * @property string|null $address_city
 * @property string|null $address_province
 * @property string|null $address_country
 * @property string|null $address_postal_code
 * @property string|null $account_number
 * @property int|null $bank_account
 * @property int|null $currency_id
 * @property string|null $payment_address_civic
 * @property string|null $payment_address_apartment
 * @property string|null $payment_address_street
 * @property string|null $payment_address_city
 * @property string|null $payment_address_province
 * @property string|null $payment_address_country
 * @property string|null $payment_address_postal_code
 * @property string|null $order_email
 * @property float $base_multiplier
 * @property float $customs_fee
 * @property float $shipping_fee
 * @property float|null $prepaid_amount
 * @property bool $collect
 * @property int|null $default_shipping_supplier_id
 * @property float $price_multiplier
 * @property bool $orderable
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type', 'name', 'phone', 'email',
    'address_civic', 'address_apartment', 'address_street', 'address_city',
    'address_province', 'address_country', 'address_postal_code',
    'account_number', 'bank_account', 'currency_id',
    'payment_address_civic', 'payment_address_apartment', 'payment_address_street',
    'payment_address_city', 'payment_address_province', 'payment_address_country',
    'payment_address_postal_code',
    'order_email', 'base_multiplier', 'customs_fee', 'shipping_fee', 'prepaid_amount', 'collect', 'default_shipping_supplier_id', 'price_multiplier', 'orderable', 'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $attributes = [
        'base_multiplier' => 2,
        'customs_fee' => 0,
        'shipping_fee' => 0,
        'prepaid_amount' => 0,
        'collect' => false,
    ];

    protected $casts = [
        'type' => SupplierType::class,
        'base_multiplier' => 'float',
        'customs_fee' => 'float',
        'shipping_fee' => 'float',
        'prepaid_amount' => 'float',
        'collect' => 'boolean',
        'price_multiplier' => 'float',
        'orderable' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $supplier->price_multiplier = $supplier->computePriceMultiplier();

            if (! $supplier->is_active) {
                $supplier->orderable = false;
            }
        });
    }

    /**
     * Le taux de change provient de la devise du fournisseur (0 si aucune devise).
     */
    public function exchangeRate(): float
    {
        return (float) ($this->currency?->rate ?? 0);
    }

    public function computePriceMultiplier(): float
    {
        return round(
            (float) $this->base_multiplier
            + $this->exchangeRate()
            + (float) $this->customs_fee
            + (float) $this->shipping_fee,
            4,
        );
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function defaultShippingSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_shipping_supplier_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
