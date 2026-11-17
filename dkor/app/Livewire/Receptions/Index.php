<?php

namespace App\Livewire\Receptions;

use App\Enums\ReceptionStatus;
use App\Models\Reception;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $supplierFilter = '';

    public string $statusFilter = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'supplierFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $term = trim($this->search);
        $statusFilter = ReceptionStatus::tryFrom($this->statusFilter);

        /** Aucune réception n'est listée tant qu'aucun critère de recherche n'est saisi. */
        $hasSearchCriteria = $term !== '' || filled($this->supplierFilter) || $statusFilter !== null;

        $receptions = $hasSearchCriteria
            ? Reception::query()
                ->accessibleTo(auth()->user())
                ->with(['supplier', 'receiver', 'lines'])
                ->when(filled($this->supplierFilter), fn ($query) => $query->where('supplier_id', (int) $this->supplierFilter))
                ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter))
                ->when($term !== '', function ($query) use ($term) {
                    $query->where(function ($q) use ($term) {
                        $q->whereLike('number', '%'.$term.'%')
                            ->orWhereLike('reference', '%'.$term.'%');
                    });
                })
                ->latest('id')
                ->paginate(10)
            : new LengthAwarePaginator([], 0, 10);

        return view('livewire.receptions.index', [
            'receptions' => $receptions,
            'hasSearchCriteria' => $hasSearchCriteria,
            'statuses' => ReceptionStatus::cases(),
            'suppliers' => Supplier::query()
                ->whereHas('receptions', fn ($query) => $query->accessibleTo(auth()->user()))
                ->orderBy('name')
                ->get(['id', 'name']),
        ])
            ->layout('layouts.app', ['title' => __('Réceptions')]);
    }
}
