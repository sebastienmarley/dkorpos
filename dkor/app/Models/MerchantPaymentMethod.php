<?php

namespace App\Models;

use Database\Factories\MerchantPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $account_type_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'account_type_id', 'is_active'])]
class MerchantPaymentMethod extends Model
{
    /** @use HasFactory<MerchantPaymentMethodFactory> */
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
