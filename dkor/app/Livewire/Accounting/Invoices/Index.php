<?php

namespace App\Livewire\Accounting\Invoices;

use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Reception;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Point d'entrée de la facturation fournisseurs: on retrouve ce qui est à facturer — une réception de produits
 * (RC-…) ou une commande de services complétée (CF-…) — par son numéro, le numéro du bon de commande ou le quote #.
 */
class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 10;

    private const MAX_PER_SOURCE = 200;

    #[Url]
    public string $search = '';

    public bool $includeInvoiced = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIncludeInvoiced(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, array{key: string, number: string, kind: string, url: string, supplier: string, orders: string, quotes: string, date: CarbonInterface, units: int, invoice: SupplierInvoice|null}>
     */
    private function receptionRows(string $term): array
    {
        return Reception::query()
            ->where('status', ReceptionStatus::Completed)
            ->whereHas('invoiceableLines')
            ->with(['supplier', 'invoice', 'invoiceableLines.orderLine.order'])
            ->when(! $this->includeInvoiced, fn ($query) => $query->whereDoesntHave('invoice'))
            ->when($term !== '', fn ($query) => $query->where(function ($q) use ($term) {
                $q->where('number', 'like', '%'.$term.'%')
                    ->orWhereHas('lines.orderLine.order', fn ($order) => $order
                        ->where('number', 'like', '%'.$term.'%')
                        ->orWhere('quote_number', 'like', '%'.$term.'%'));
            }))
            ->latest('id')
            ->limit(self::MAX_PER_SOURCE)
            ->get()
            ->map(function (Reception $reception): array {
                $orders = $reception->invoiceableLines->map(fn ($line) => $line->orderLine->order)->unique('id');

                return [
                    'key' => 'reception-'.$reception->id,
                    'number' => (string) $reception->number,
                    'kind' => (string) __('Produits'),
                    'url' => route('accounting.invoices.reception', $reception),
                    'supplier' => $reception->supplier->name,
                    'orders' => (string) $orders->pluck('number')->join(', '),
                    'quotes' => (string) $orders->pluck('quote_number')->filter()->join(', '),
                    'date' => $reception->completed_at ?? $reception->received_at,
                    'units' => (int) $reception->invoiceableLines->sum('quantity_net'),
                    'invoice' => $reception->invoice,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{key: string, number: string, kind: string, url: string, supplier: string, orders: string, quotes: string, date: CarbonInterface, units: int, invoice: SupplierInvoice|null}>
     */
    private function serviceRows(string $term): array
    {
        return SupplierOrder::query()
            ->where('type', SupplierType::Service)
            ->whereIn('status', $this->includeInvoiced
                ? [SupplierOrderStatus::Received, SupplierOrderStatus::Invoiced]
                : [SupplierOrderStatus::Received])
            ->with(['supplier', 'supplierInvoice', 'lines'])
            ->when($term !== '', fn ($query) => $query->where(function ($q) use ($term) {
                $q->where('number', 'like', '%'.$term.'%')
                    ->orWhere('quote_number', 'like', '%'.$term.'%');
            }))
            ->latest('id')
            ->limit(self::MAX_PER_SOURCE)
            ->get()
            ->map(fn (SupplierOrder $order): array => [
                'key' => 'order-'.$order->id,
                'number' => (string) $order->number,
                'kind' => (string) __('Services'),
                'url' => route('accounting.invoices.order', $order),
                'supplier' => $order->supplier->name,
                'orders' => (string) $order->number,
                'quotes' => (string) $order->quote_number,
                'date' => $order->received_at ?? now(),
                'units' => (int) $order->lines->reject(fn ($line) => $line->status->isClosed())->sum('quantity'),
                'invoice' => $order->supplierInvoice,
            ])
            ->all();
    }

    /**
     * Factures libres (sans réception ni commande): toujours facturées, donc affichées avec « Inclure les facturées ».
     *
     * @return array<int, array{key: string, number: string, kind: string, url: string, supplier: string, orders: string, quotes: string, date: CarbonInterface, units: int, invoice: SupplierInvoice|null}>
     */
    private function standaloneRows(string $term): array
    {
        if (! $this->includeInvoiced) {
            return [];
        }

        return SupplierInvoice::query()
            ->whereNull('reception_id')
            ->whereNull('supplier_order_id')
            ->with('supplier')
            ->when($term !== '', fn ($query) => $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$term.'%'));
            }))
            ->latest('id')
            ->limit(self::MAX_PER_SOURCE)
            ->get()
            ->map(fn (SupplierInvoice $invoice): array => [
                'key' => 'invoice-'.$invoice->id,
                'number' => $invoice->invoice_number,
                'kind' => (string) __('Libre'),
                'url' => route('accounting.invoices.show', $invoice),
                'supplier' => $invoice->supplier->name,
                'orders' => '—',
                'quotes' => '',
                'date' => $invoice->invoice_date,
                'units' => 0,
                'invoice' => $invoice,
            ])
            ->all();
    }

    public function render(): View
    {
        $term = trim($this->search);

        $rows = collect($this->receptionRows($term))
            ->concat($this->serviceRows($term))
            ->concat($this->standaloneRows($term))
            ->sortByDesc(fn (array $row) => $row['date']->getTimestamp())
            ->values();

        $page = $this->getPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );

        return view('livewire.accounting.invoices.index', ['rows' => $paginator])
            ->layout('layouts.app', ['title' => __('Facturation fournisseurs')]);
    }
}
