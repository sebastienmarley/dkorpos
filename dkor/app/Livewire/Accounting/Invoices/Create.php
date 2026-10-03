<?php

namespace App\Livewire\Accounting\Invoices;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Facture rattachée à un fournisseur seulement, sans lien avec une réception ni une commande.
 */
class Create extends Component
{
    public string $supplierId = '';

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $description = '';

    public string $merchandiseTotal = '';

    public string $freightFee = '0';

    public string $customsFee = '0';

    public string $taxes = '0';

    public string $invoiceTotal = '';

    public function mount(): void
    {
        $this->invoiceDate = now()->toDateString();
    }

    public function save(): void
    {
        $this->authorize('invoices.create');

        $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', 1)],
            'invoiceNumber' => [
                'required', 'string', 'max:100',
                Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', $this->supplierId),
            ],
            'invoiceDate' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'merchandiseTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'freightFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'customsFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'taxes' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'invoiceTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        try {
            $invoice = SupplierInvoice::recordStandalone(Supplier::findOrFail($this->supplierId), [
                'invoice_number' => $this->invoiceNumber,
                'invoice_date' => $this->invoiceDate,
                'description' => $this->description,
                'merchandise_total' => $this->merchandiseTotal,
                'freight_fee' => $this->freightFee,
                'customs_fee' => $this->customsFee,
                'taxes' => $this->taxes,
                'invoice_total' => $this->invoiceTotal,
            ], auth()->id());
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('accounting.invoices.show', $invoice, navigate: true);
    }

    public function render(): View
    {
        $supplier = filled($this->supplierId) ? Supplier::find($this->supplierId) : null;

        return view('livewire.accounting.invoices.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'totals' => $supplier ? SupplierInvoice::totals(
                $supplier,
                collect(),
                [],
                (float) $this->freightFee,
                (float) $this->customsFee,
                (float) $this->taxes,
                (float) $this->invoiceTotal,
                Carbon::canBeCreatedFromFormat($this->invoiceDate, 'Y-m-d') ? Carbon::parse($this->invoiceDate) : null,
                (float) $this->merchandiseTotal,
            ) : null,
        ])->layout('layouts.app', ['title' => __('Nouvelle facture')]);
    }
}
