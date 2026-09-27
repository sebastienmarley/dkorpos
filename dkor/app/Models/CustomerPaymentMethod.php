<?php

namespace App\Models;

use Database\Factories\CustomerPaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_active'])]
class CustomerPaymentMethod extends Model
{
    /** @use HasFactory<CustomerPaymentMethodFactory> */
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
