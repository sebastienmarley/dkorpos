<?php

namespace App\Livewire\Accounting\Invoices;

use App\Enums\ReceptionStatus;
use App\Models\Reception;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Point d'entrée de la facturation fournisseurs: on retrouve une réception (RC-…) par son numéro, par le numéro
 * du bon de commande ou par le quote #, puis on ouvre sa facturation.
 */
class Index extends Component
{
    use WithPagination;

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

    public function render(): View
    {
        $term = trim($this->search);

        $receptions = Reception::query()
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
            ->latest('completed_at')
            ->latest('id')
            ->paginate(10);

        return view('livewire.accounting.invoices.index', ['receptions' => $receptions])
            ->layout('layouts.app', ['title' => __('Facturation fournisseurs')]);
    }
}
