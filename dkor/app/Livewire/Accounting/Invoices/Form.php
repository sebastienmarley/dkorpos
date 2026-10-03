<?php

namespace App\Livewire\Accounting\Invoices;

use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Saisie (ou consultation) de la facture d'une réception de produits ou d'une commande de services complétée.
 */
class Form extends Component
{
    public ?Reception $reception = null;

    public ?SupplierOrder $order = null;

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $freightFee = '0';

    public string $customsFee = '0';

    public string $taxes = '0';

    public string $invoiceTotal = '';

    /** @var array<int, string> Coût réel par ligne (de réception ou de commande de services). */
    public array $unitCosts = [];

    public function mount(?Reception $reception = null, ?SupplierOrder $order = null): void
    {
        if ($reception !== null) {
            abort_unless($reception->status === ReceptionStatus::Completed, 404);
        } else {
            abort_unless(
                $order !== null
                && $order->type === SupplierType::Service
                && in_array($order->status, [SupplierOrderStatus::Received, SupplierOrderStatus::Invoiced], true),
                404,
            );
        }

        $this->reception = $reception;
        $this->order = $order;
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
                Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', $this->supplier()->id),
            ],
            'invoiceDate' => ['required', 'date'],
            'freightFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'customsFee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'taxes' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'invoiceTotal' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unitCosts.*' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $details = [
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => $this->invoiceDate,
            'freight_fee' => $this->freightFee,
            'customs_fee' => $this->customsFee,
            'taxes' => $this->taxes,
            'invoice_total' => $this->invoiceTotal,
        ];

        try {
            if ($this->reception) {
                SupplierInvoice::record($this->reception, $details, $this->unitCosts, auth()->id());
            } else {
                SupplierInvoice::recordForOrder($this->order, $details, $this->unitCosts, auth()->id());
            }
        } catch (DomainException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->reception?->unsetRelation('invoice');
        $this->order?->refresh()->unsetRelation('supplierInvoice');

        Flux::toast(text: __('Facture enregistrée.'), variant: 'success');
    }

    private function supplier(): Supplier
    {
        return $this->reception ? $this->reception->supplier : $this->order->supplier;
    }

    /**
     * Lignes à facturer: lignes de réception encore comptées, ou lignes actives d'une commande de services.
     *
     * @return Collection<int, ReceptionLine>|Collection<int, SupplierOrderLine>
     */
    private function lines(): Collection
    {
        if ($this->reception) {
            return $this->reception->invoiceableLines()->with(['product', 'orderLine.order'])->get()->toBase();
        }

        return $this->order->lines()->get()
            ->reject(fn (SupplierOrderLine $line) => $line->status->isClosed())
            ->values()
            ->toBase();
    }

    public function render(): View
    {
        $document = $this->reception ?? $this->order;
        $invoice = $this->reception
            ? $this->reception->invoice()->with('lines.receptionLine.orderLine')->first()
            : $this->order->supplierInvoice()->with('lines.orderLine')->first();
        $lines = $this->lines();

        return view('livewire.accounting.invoices.form', [
            'number' => $document->number,
            'supplier' => $this->supplier(),
            'isService' => $this->reception === null,
            'documentDate' => $this->reception
                ? ($this->reception->completed_at ?? $this->reception->received_at)
                : ($this->order->received_at ?? $this->order->updated_at),
            'invoice' => $invoice,
            'lines' => $lines,
            'orders' => $this->reception
                ? $this->reception->invoiceableLines()->with('orderLine.order')->get()->map(fn (ReceptionLine $line) => $line->orderLine->order)->unique('id')
                : collect([$this->order]),
            'totals' => $invoice ? null : SupplierInvoice::totals(
                $this->supplier(),
                $lines,
                array_map('floatval', $this->unitCosts),
                (float) $this->freightFee,
                (float) $this->customsFee,
                (float) $this->taxes,
                (float) $this->invoiceTotal,
                Carbon::canBeCreatedFromFormat($this->invoiceDate, 'Y-m-d') ? Carbon::parse($this->invoiceDate) : null,
            ),
        ])->layout('layouts.app', ['title' => __('Facturation :number', ['number' => $document->number])]);
    }
}
