<?php

namespace App\Models;

use Database\Factories\ReceptionLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reception_id
 * @property int $supplier_order_line_id
 * @property int|null $product_id
 * @property int $quantity
 * @property int $quantity_damaged Partie de la quantité reçue endommagée (inventaire défectueux).
 * @property float $unit_cost
 * @property int $quantity_reversed
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reception $reception
 * @property-read SupplierOrderLine $orderLine
 * @property-read Product|null $product
 * @property-read float $total
 * @property-read int $quantity_net
 * @property-read int $billable_quantity
 * @property-read string $label
 */
#[Fillable(['reception_id', 'supplier_order_line_id', 'product_id', 'quantity', 'quantity_damaged', 'unit_cost', 'quantity_reversed', 'reversed_at', 'reversed_by', 'reversal_reason'])]
class ReceptionLine extends Model
{
    /** @use HasFactory<ReceptionLineFactory> */
    use HasFactory;

    protected $casts = [
        'quantity_damaged' => 'integer',
        'quantity' => 'integer',
        'unit_cost' => 'float',
        'quantity_reversed' => 'integer',
        'reversed_at' => 'datetime',
    ];

    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /** @return BelongsTo<SupplierOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderLine::class, 'supplier_order_line_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Quantité encore comptée après les renversements. */
    public function getQuantityNetAttribute(): int
    {
        return $this->quantity - $this->quantity_reversed;
    }

    public function getBillableQuantityAttribute(): int
    {
        return $this->quantity_net;
    }

    public function getLabelAttribute(): string
    {
        return $this->orderLine->label;
    }

    public function getTotalAttribute(): float
    {
        return round($this->quantity_net * $this->unit_cost, 2);
    }

    /**
     * Renverse (en tout ou en partie) cette réception en cas d'erreur de réception.
     */
    public function reverse(int $quantity, ?string $reason = null, int|string|null $reversedBy = null): void
    {
        $this->orderLine->order->reverseReceipt($this, $quantity, $reason, $reversedBy);
    }
}
