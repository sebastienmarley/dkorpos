<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showInactive = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowInactive(): void
    {
        $this->resetPage();
    }

    #[On('supplier-saved')]
    public function refresh(): void {}

    public function render(): View
    {
        $suppliers = Supplier::query()
            ->when(! $this->showInactive, fn ($query) => $query->where('is_active', true))
            ->when(filled($this->search), fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(5);

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['title' => __('Fournisseurs')]);
    }
}
