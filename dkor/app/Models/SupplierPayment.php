<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SupplierPaymentMethod;
use App\Support\Money;
use Database\Factories\SupplierPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paiement fait à un fournisseur (#132, ADR 0016), réparti sur ses factures. Le montant est dans la devise du
 * fournisseur; le montant CAD est ce qui a réellement quitté le compte d'encaisse. Un paiement ne se supprime pas :
 * il s'annule, avec une écriture inverse (App\Actions\CancelSupplierPayment).
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $ledger_account_id Compte d'encaisse payeur.
 * @property Carbon $paid_on
 * @property SupplierPaymentMethod $method
 * @property string|null $reference Numéro de chèque, de virement…
 * @property Money $amount
 * @property Money $amount_cad
 * @property int|null $created_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read LedgerAccount $account
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 * @property-read User|null $creator
 */
#[Fillable(['supplier_id', 'ledger_account_id', 'paid_on', 'method', 'reference', 'amount', 'amount_cad', 'created_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class SupplierPayment extends Model
{
    /** @use HasFactory<SupplierPaymentFactory> */
    use HasFactory;

    protected $casts = [
        'paid_on' => 'date',
        'method' => SupplierPaymentMethod::class,
        'amount' => MoneyCast::class,
        'amount_cad' => MoneyCast::class,
        'cancelled_at' => 'datetime',
    ];

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * @param  Builder<SupplierPayment>  $query
     * @return Builder<SupplierPayment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
