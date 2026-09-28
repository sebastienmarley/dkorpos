<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $supplierId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function getProducts(): LengthAwarePaginator
    {
        return Product::with('supplier')
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('model', 'like', '%'.$this->search.'%')
                        ->orWhere('supplier_model', 'like', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when(filled($this->supplierId), fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->orderBy('model')
            ->paginate(10);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::orderBy('name')->get();
    }

    #[On('product-saved')]
    public function refreshProducts(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.products.index', [
            'products' => $this->getProducts(),
            'suppliers' => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => __('Produits')]);
    }
}
