<?php

namespace App\Models;

use App\Enums\CustomerPaymentType;
use Database\Factories\CustomerOrderPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_order_id
 * @property int|null $customer_order_pickup_id
 * @property int|null $customer_payment_method_id
 * @property CustomerPaymentType $type
 * @property float $amount
 * @property int|null $received_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 * @property-read CustomerOrderPickup|null $pickup
 * @property-read CustomerPaymentMethod|null $paymentMethod
 * @property-read User|null $receiver
 */
#[Fillable(['customer_order_id', 'customer_order_pickup_id', 'customer_payment_method_id', 'type', 'amount', 'received_by'])]
class CustomerOrderPayment extends Model
{
    /** @use HasFactory<CustomerOrderPaymentFactory> */
    use HasFactory;

    protected $casts = [
        'amount' => 'float',
        'type' => CustomerPaymentType::class,
    ];

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return BelongsTo<CustomerOrderPickup, $this> */
    public function pickup(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderPickup::class, 'customer_order_pickup_id');
    }

    /** @return BelongsTo<CustomerPaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentMethod::class, 'customer_payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
