<?php

namespace App\Livewire\Stores;

use App\Models\Store;
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

    #[On('store-saved')]
    public function refresh(): void {}

    public function render(): View
    {
        $stores = Store::query()
            ->when(! $this->showInactive, fn ($query) => $query->where('is_active', true))
            ->when(filled($this->search), fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.stores.index', [
            'stores' => $stores,
        ])->layout('layouts.app', ['title' => __('Magasins')]);
    }
}
