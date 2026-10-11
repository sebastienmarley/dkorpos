<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\SupplierInvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int|null $reception_line_id
 * @property int|null $supplier_order_line_id
 * @property int $quantity
 * @property Money $unit_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierInvoice $invoice
 * @property-read ReceptionLine|null $receptionLine
 * @property-read SupplierOrderLine|null $orderLine
 * @property-read string $label
 * @property-read Money $total
 */
#[Fillable(['supplier_invoice_id', 'reception_line_id', 'supplier_order_line_id', 'quantity', 'unit_cost'])]
class SupplierInvoiceLine extends Model
{
    /** @use HasFactory<SupplierInvoiceLineFactory> */
    use HasFactory;

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => MoneyCast::class,
    ];

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    /** @return BelongsTo<ReceptionLine, $this> */
    public function receptionLine(): BelongsTo
    {
        return $this->belongsTo(ReceptionLine::class);
    }

    /** @return BelongsTo<SupplierOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderLine::class, 'supplier_order_line_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->reception_line_id !== null ? $this->receptionLine->label : (string) $this->orderLine->label;
    }

    public function getTotalAttribute(): Money
    {
        return $this->unit_cost->times($this->quantity);
    }
}
