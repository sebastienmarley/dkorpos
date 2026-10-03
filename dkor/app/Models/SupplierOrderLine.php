<?php

namespace App\Models;

use App\Enums\SupplierOrderLineStatus;
use Database\Factories\SupplierOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_order_id
 * @property int|null $product_id
 * @property string|null $description
 * @property int $quantity
 * @property float $unit_cost
 * @property int $quantity_received
 * @property SupplierOrderLineStatus $status
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancellation_requested_at
 * @property Carbon|null $cancelled_at
 * @property int|null $substituted_from_line_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierOrder $order
 * @property-read Product|null $product
 * @property-read float $total
 * @property-read int $quantity_outstanding
 * @property-read int $billable_quantity
 */
#[Fillable(['supplier_order_id', 'product_id', 'description', 'quantity', 'unit_cost', 'quantity_received', 'status', 'cancellation_reason', 'cancellation_requested_at', 'cancelled_at', 'substituted_from_line_id'])]
class SupplierOrderLine extends Model
{
    /** @use HasFactory<SupplierOrderLineFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity_received' => 0,
        'status' => 'active',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'float',
        'quantity_received' => 'integer',
        'status' => SupplierOrderLineStatus::class,
        'cancellation_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<SupplierOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    /** @return BelongsTo<SupplierOrderLine, $this> */
    public function substitutedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituted_from_line_id');
    }

    /** @return HasOne<SupplierOrderLine, $this> */
    public function substitutedBy(): HasOne
    {
        return $this->hasOne(self::class, 'substituted_from_line_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalAttribute(): float
    {
        return round($this->quantity * $this->unit_cost, 2);
    }

    public function getQuantityOutstandingAttribute(): int
    {
        if ($this->status->isClosed()) {
            return 0;
        }

        return max(0, $this->quantity - $this->quantity_received);
    }

    /**
     * Substitue le produit de la ligne par un autre (utilisable depuis les ventes).
     */
    public function substituteWith(Product $product): SupplierOrderLine
    {
        return $this->order->substituteLine($this, $product);
    }

    /**
     * Demande l'annulation de la ligne au fournisseur (utilisable depuis les commandes comme depuis les ventes).
     *
     * @return bool Vrai si le courriel de demande a pu être envoyé au fournisseur.
     */
    public function requestCancellation(?string $reason = null): bool
    {
        return $this->order->requestLineCancellation($this, $reason);
    }

    /** Quantité à facturer: ce qui est reçu pour un produit, la quantité commandée pour un service. */
    public function getBillableQuantityAttribute(): int
    {
        return $this->product_id !== null ? $this->quantity_received : $this->quantity;
    }

    public function getLabelAttribute(): string
    {
        return $this->product_id !== null ? $this->product->display_name : (string) $this->description;
    }
}
