<?php

namespace App\Models;

use App\Enums\SupplierType;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property SupplierType $type
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $account_number
 * @property int|null $bank_account
 * @property string|null $payment_address
 * @property string|null $order_email
 * @property bool $orderable
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'name', 'address', 'phone', 'email', 'account_number', 'bank_account', 'payment_address', 'order_email', 'orderable', 'is_active'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $casts = [
        'type' => SupplierType::class,
        'orderable' => 'boolean',
        'is_active' => 'boolean',
    ];
}
