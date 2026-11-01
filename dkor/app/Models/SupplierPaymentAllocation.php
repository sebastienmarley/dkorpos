<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\SupplierPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part d'un paiement appliquée à une facture : montant payé et escompte de paiement rapide, dans la devise du
 * fournisseur, et montant soldé aux comptes fournisseurs en CAD (au taux de la facture).
 *
 * @property int $id
 * @property int $supplier_payment_id
 * @property int $supplier_invoice_id
 * @property Money $amount
 * @property Money $discount
 * @property Money $payable_cad
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierPayment $payment
 * @property-read SupplierInvoice $invoice
 */
#[Fillable(['supplier_payment_id', 'supplier_invoice_id', 'amount', 'discount', 'payable_cad'])]
class SupplierPaymentAllocation extends Model
{
    /** @use HasFactory<SupplierPaymentAllocationFactory> */
    use HasFactory;

    protected $casts = [
        'amount' => MoneyCast::class,
        'discount' => MoneyCast::class,
        'payable_cad' => MoneyCast::class,
    ];

    /** Montant soldé sur la facture : le paiement et l'escompte. */
    public function settled(): Money
    {
        return $this->amount->plus($this->discount);
    }

    /** @return BelongsTo<SupplierPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }
}
