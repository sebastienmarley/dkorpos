<?php

namespace App\Models;

use App\Enums\SupplierType;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property string|null $payment_address_civic
 * @property string|null $payment_address_apartment
 * @property string|null $payment_address_street
 * @property string|null $payment_address_city
 * @property string|null $payment_address_province
 * @property string|null $payment_address_country
 * @property string|null $payment_address_postal_code
 * @property string|null $order_email
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
    'account_number', 'bank_account',
    'payment_address_civic', 'payment_address_apartment', 'payment_address_street',
    'payment_address_city', 'payment_address_province', 'payment_address_country',
    'payment_address_postal_code',
    'order_email', 'price_multiplier', 'orderable', 'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $casts = [
        'type' => SupplierType::class,
        'price_multiplier' => 'float',
        'orderable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
