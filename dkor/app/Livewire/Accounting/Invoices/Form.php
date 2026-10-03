<?php

namespace App\Livewire\Accounting\Invoices;

use App\Enums\ReceptionStatus;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\SupplierInvoice;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public Reception $reception;

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $freightFee = '0';

    public string $customsFee = '0';

    public string $taxes = '0';

    public string $invoiceTotal = '';

    /** @var array<int, string> Coût réel par ligne de réception. */
    public array $unitCosts = [];

    public function mount(Reception $reception): void
    {
        abort_unless($reception->status === ReceptionStatus::Completed, 404);

        $this->reception = $reception;
        $this->invoiceDate = now()->toDateString();

        foreach ($this->lines() as $line) {
            $this->unitCosts[$line->id] = number_format($line->unit_cost, 2, '.', '');
        }
    }

    public function save(): void
    {
        $this->authorize('invoices.create');

        $this->validate([
            'invoiceNumber' => [
                'required', 'string', 'max:100',
                Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', $this->reception->supplier_id),
            ],
            'invoiceDate' => ['required', 'date'],
            'freightFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'customsFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'taxes' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'invoiceTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unitCosts.*' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            SupplierInvoice::record($this->reception, [
                'invoice_number' => $this->invoiceNumber,
                'invoice_date' => $this->invoiceDate,
                'freight_fee' => $this->freightFee,
                'customs_fee' => $this->customsFee,
                'taxes' => $this->taxes,
                'invoice_total' => $this->invoiceTotal,
            ], $this->unitCosts, auth()->id());
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->reception->refresh()->unsetRelation('invoice');

        Flux::toast(text: __('Facture enregistrée.'), variant: 'success');
    }

    /** @return Collection<int, ReceptionLine> */
    private function lines(): Collection
    {
        return $this->reception->invoiceableLines()->with(['product', 'orderLine.order'])->get();
    }

    public function render(): View
    {
        $invoice = $this->reception->invoice()->with('lines.receptionLine.orderLine.order')->first();
        $lines = $this->lines();

        return view('livewire.accounting.invoices.form', [
            'invoice' => $invoice,
            'lines' => $lines,
            'orders' => $lines->map(fn ($line) => $line->orderLine->order)->unique('id'),
            'totals' => $invoice ? null : SupplierInvoice::totals(
                $this->reception->supplier,
                $lines,
                array_map('floatval', $this->unitCosts),
                (float) $this->freightFee,
                (float) $this->customsFee,
                (float) $this->taxes,
                (float) $this->invoiceTotal,
                Carbon::canBeCreatedFromFormat($this->invoiceDate, 'Y-m-d') ? Carbon::parse($this->invoiceDate) : null,
            ),
        ])->layout('layouts.app', ['title' => __('Facturation :number', ['number' => $this->reception->number])]);
    }
}
