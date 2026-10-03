<?php

namespace App\Models;

use Database\Factories\SupplierInvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int $reception_line_id
 * @property int $quantity
 * @property float $unit_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierInvoice $invoice
 * @property-read ReceptionLine $receptionLine
 * @property-read float $total
 */
#[Fillable(['supplier_invoice_id', 'reception_line_id', 'quantity', 'unit_cost'])]
class SupplierInvoiceLine extends Model
{
    /** @use HasFactory<SupplierInvoiceLineFactory> */
    use HasFactory;

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'float',
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

    public function getTotalAttribute(): float
    {
        return round($this->quantity * $this->unit_cost, 2);
    }
}
