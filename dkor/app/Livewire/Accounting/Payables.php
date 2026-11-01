<?php

namespace App\Livewire\Accounting;

use App\Actions\CancelSupplierPayment;
use App\Actions\RecordSupplierPayment;
use App\Enums\LedgerAccountType;
use App\Enums\SupplierPaymentMethod;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Support\Money;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Comptes à payer (#132, ADR 0016) : les factures fournisseur qui ont un solde dû, les escomptes de paiement rapide
 * qui arrivent à échéance, et les paiements, que l'on enregistre en les répartissant sur les factures ou que l'on
 * annule.
 */
class Payables extends Component
{
    /** Nombre de jours avant l'échéance d'un escompte à partir duquel la facture est mise en évidence. */
    public const DISCOUNT_WARNING_DAYS = 7;

    #[Url(as: 'supplier')]
    public string $supplierFilter = '';

    public bool $showPayment = false;

    public ?int $paymentSupplierId = null;

    public string $paidOn = '';

    public string $method = '';

    public string $reference = '';

    public string $cashAccountId = '';

    public string $amountCad = '';

    /** @var array<int, string> Montant payé par facture, en dollars de la devise du fournisseur. */
    public array $amounts = [];

    /** @var array<int, bool> Escompte appliqué par facture. */
    public array $discounts = [];

    public bool $showCancel = false;

    public ?int $cancellingId = null;

    public string $cancellationReason = '';

    public function openPayment(Supplier $supplier): void
    {
        $this->authorize('supplier_payments.create');

        $this->resetValidation();
        $this->paymentSupplierId = $supplier->id;
        $this->paidOn = today()->toDateString();
        $this->method = SupplierPaymentMethod::Cheque->value;
        $this->reference = '';
        $this->amountCad = '';
        $this->cashAccountId = (string) LedgerAccount::forKey($supplier->currency?->code === 'USD' ? LedgerAccount::CASH_USD : LedgerAccount::CASH_CAD)->id;
        $this->amounts = [];
        $this->discounts = [];

        foreach ($this->unpaidInvoices($supplier->id) as $invoice) {
            $this->amounts[$invoice->id] = '';
            $this->discounts[$invoice->id] = false;
        }

        $this->showPayment = true;
    }

    /**
     * Paie le solde dû d'une facture, moins son escompte s'il s'applique encore à la date du paiement.
     */
    public function payInFull(int $invoiceId): void
    {
        $invoice = $this->unpaidInvoices((int) $this->paymentSupplierId)->firstWhere('id', $invoiceId);

        if ($invoice === null) {
            return;
        }

        $date = Carbon::canBeCreatedFromFormat($this->paidOn, 'Y-m-d') ? Carbon::parse($this->paidOn) : today();
        $this->discounts[$invoiceId] = $invoice->discountAvailableOn($date);
        $this->amounts[$invoiceId] = $invoice->balanceDue()->minus($this->discounts[$invoiceId] ? $invoice->discount_amount : Money::zero())->toDecimal();
    }

    public function savePayment(): void
    {
        $this->authorize('supplier_payments.create');

        $supplier = Supplier::query()->findOrFail($this->paymentSupplierId);

        $this->validate([
            'paidOn' => ['required', 'date'],
            'method' => ['required', Rule::enum(SupplierPaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'cashAccountId' => ['required', 'integer', Rule::exists('ledger_accounts', 'id')->where('type', LedgerAccountType::Asset->value)],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'amountCad' => [$supplier->conversionRate() === '1' ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $allocations = [];

        foreach ($this->amounts as $invoiceId => $amount) {
            $allocations[(int) $invoiceId] = ['amount' => Money::tryParse($amount) ?? Money::zero(), 'discount' => (bool) ($this->discounts[$invoiceId] ?? false)];
        }

        try {
            app(RecordSupplierPayment::class)->handle(
                auth()->user(),
                $supplier,
                LedgerAccount::query()->findOrFail($this->cashAccountId),
                Carbon::parse($this->paidOn),
                SupplierPaymentMethod::from($this->method),
                $this->reference,
                $allocations,
                Money::tryParse($this->amountCad) ?? Money::zero(),
            );
        } catch (DomainException $e) {
            $this->addError('amounts', $e->getMessage());

            return;
        }

        $this->showPayment = false;

        Flux::toast(text: __('Paiement enregistré.'), variant: 'success');
    }

    public function openCancel(SupplierPayment $payment): void
    {
        $this->authorize('supplier_payments.create');

        $this->resetValidation();
        $this->cancellingId = $payment->id;
        $this->cancellationReason = '';
        $this->showCancel = true;
    }

    public function cancelPayment(): void
    {
        $this->authorize('supplier_payments.create');

        $this->validate(['cancellationReason' => ['required', 'string', 'max:255']]);

        try {
            app(CancelSupplierPayment::class)->handle(auth()->user(), SupplierPayment::query()->findOrFail($this->cancellingId), $this->cancellationReason);
        } catch (DomainException $e) {
            $this->addError('cancellationReason', $e->getMessage());

            return;
        }

        $this->showCancel = false;

        Flux::toast(text: __('Paiement annulé.'), variant: 'success');
    }

    /**
     * Factures avec un solde dû, du fournisseur donné ou de tous, les plus anciennes d'abord.
     *
     * @return Collection<int, SupplierInvoice>
     */
    private function unpaidInvoices(?int $supplierId = null): Collection
    {
        return SupplierInvoice::query()
            ->unpaid()
            ->with(['supplier.currency', 'paymentAllocations.payment'])
            ->when($supplierId !== null, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        $invoices = $this->unpaidInvoices(filled($this->supplierFilter) ? (int) $this->supplierFilter : null);

        return view('livewire.accounting.payables', [
            'invoicesBySupplier' => $invoices->groupBy('supplier_id'),
            'totalCad' => Money::sum($invoices->map(fn (SupplierInvoice $invoice): Money => $invoice->balanceDue()->multiply($invoice->exchange_rate))->all()),
            'warningDate' => today()->addDays(self::DISCOUNT_WARNING_DAYS),
            'suppliers' => Supplier::query()->whereIn('id', SupplierInvoice::query()->select('supplier_id'))->orderBy('name')->get(),
            'payments' => SupplierPayment::query()
                ->with(['supplier.currency', 'account', 'allocations.invoice'])
                ->when(filled($this->supplierFilter), fn (Builder $query) => $query->where('supplier_id', (int) $this->supplierFilter))
                ->latest('paid_on')
                ->latest('id')
                ->limit(25)
                ->get(),
            'paymentSupplier' => $this->paymentSupplierId ? Supplier::with('currency')->find($this->paymentSupplierId) : null,
            'paymentInvoices' => $this->showPayment ? $this->unpaidInvoices($this->paymentSupplierId) : new Collection,
            'cashAccounts' => LedgerAccount::query()->where('type', LedgerAccountType::Asset)->orderBy('code')->get(),
            'methods' => SupplierPaymentMethod::cases(),
        ])->layout('layouts.app', ['title' => __('Comptes à payer')]);
    }
}
