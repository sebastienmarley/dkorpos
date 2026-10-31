<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
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
 * @property int $quantity_expected Quantité attendue selon le bordereau du fournisseur.
 * @property int $quantity_damaged Partie de la quantité reçue endommagée (inventaire défectueux).
 * @property int $quantity_short Quantité annoncée (et facturée) par le fournisseur, mais non reçue.
 * @property Carbon|null $checked_at Moment où la quantité reçue a été vérifiée.
 * @property Money $unit_cost
 * @property int $quantity_reversed
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reception $reception
 * @property-read SupplierOrderLine $orderLine
 * @property-read Product|null $product
 * @property-read Money $total
 * @property-read int $quantity_net
 * @property-read int $billable_quantity
 * @property-read string $label
 */
#[Fillable(['reception_id', 'supplier_order_line_id', 'product_id', 'quantity', 'quantity_expected', 'quantity_damaged', 'quantity_short', 'checked_at', 'unit_cost', 'quantity_reversed', 'reversed_at', 'reversed_by', 'reversal_reason'])]
class ReceptionLine extends Model
{
    /** @use HasFactory<ReceptionLineFactory> */
    use HasFactory;

    protected $casts = [
        'quantity_damaged' => 'integer',
        'quantity_short' => 'integer',
        'quantity' => 'integer',
        'quantity_expected' => 'integer',
        'checked_at' => 'datetime',
        'unit_cost' => MoneyCast::class,
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

    /**
     * Clé du produit auquel la ligne est regroupée dans la réception : les lignes d'un même produit se reçoivent
     * ensemble. Une ligne libre (sans produit) et un article reçu en trop restent seuls.
     */
    public function groupKey(): string
    {
        return $this->product_id === null || $this->orderLine->is_overship ? 'line-'.$this->id : 'product-'.$this->product_id;
    }

    /** La ligne couvre un article commandé pour un client : elle est servie en premier. */
    public function isForCustomer(): bool
    {
        return $this->orderLine->customerOrderLine !== null;
    }

    /** Quantité encore comptée après les renversements. */
    public function getQuantityNetAttribute(): int
    {
        return $this->quantity - $this->quantity_reversed;
    }

    /** Quantité que le fournisseur facture : ce qui est reçu (après renversements) et les manquants. */
    public function getBillableQuantityAttribute(): int
    {
        return $this->quantity_net + $this->quantity_short;
    }

    public function getLabelAttribute(): string
    {
        return $this->orderLine->label;
    }

    public function getTotalAttribute(): Money
    {
        return $this->unit_cost->times($this->quantity_net);
    }

    /**
     * Renverse (en tout ou en partie) cette réception en cas d'erreur de réception.
     */
    public function reverse(int $quantity, ?string $reason = null, int|string|null $reversedBy = null): void
    {
        $this->orderLine->order->reverseReceipt($this, $quantity, $reason, $reversedBy);
    }
}
