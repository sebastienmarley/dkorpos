<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    #[On('supplier-saved')]
    public function refresh(): void {}

    public function render(): View
    {
        $suppliers = blank($this->search)
            ? Supplier::orderBy('name')->get()
            : Supplier::query()
                ->where('name', 'like', '%'.$this->search.'%')
                ->orderBy('name')
                ->get();

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['title' => __('Fournisseurs')]);
    }
}
