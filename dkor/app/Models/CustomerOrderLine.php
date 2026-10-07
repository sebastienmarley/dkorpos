<?php

namespace App\Models;

use App\Enums\CustomerOrderLineStatus;
use Database\Factories\CustomerOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_order_id
 * @property int $product_id
 * @property int $quantity
 * @property float $unit_price
 * @property string|null $note
 * @property CustomerOrderLineStatus $status
 * @property Carbon|null $delivered_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomerOrder $order
 * @property-read Product $product
 * @property-read float $total
 */
#[Fillable(['customer_order_id', 'product_id', 'quantity', 'unit_price', 'note', 'status', 'delivered_at', 'returned_at'])]
class CustomerOrderLine extends Model
{
    /** @use HasFactory<CustomerOrderLineFactory> */
    use HasFactory;

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'status' => CustomerOrderLineStatus::class,
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalAttribute(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
