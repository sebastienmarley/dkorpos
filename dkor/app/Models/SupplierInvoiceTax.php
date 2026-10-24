<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Montant d'une taxe (TPS, TVQ…) facturée par un fournisseur : chaque taxe a son compte « à recevoir » au grand livre.
 *
 * @property int $id
 * @property int $supplier_invoice_id
 * @property string $tax_name
 * @property Money $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierInvoice $invoice
 */
#[Fillable(['supplier_invoice_id', 'tax_name', 'amount'])]
class SupplierInvoiceTax extends Model
{
    protected $casts = [
        'amount' => MoneyCast::class,
    ];

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }
}
