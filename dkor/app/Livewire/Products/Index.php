<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\Department;
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

    public string $departmentId = '';

    public string $categoryId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->categoryId = '';
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function getProducts(): LengthAwarePaginator
    {
        return Product::with(['supplier', 'category.department'])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->whereLike('model', '%'.$this->search.'%')
                        ->orWhereLike('supplier_model', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn ($s) => $s->whereLike('name', '%'.$this->search.'%'));
                });
            })
            ->when(filled($this->supplierId), fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->when(filled($this->departmentId), fn ($q) => $q->whereRelation('category', 'department_id', $this->departmentId))
            ->when(filled($this->categoryId), fn ($q) => $q->where('category_id', $this->categoryId))
            ->orderBy('model')
            ->paginate(10);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::orderBy('name')->get();
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        if (blank($this->departmentId)) {
            return new Collection;
        }

        return Category::where('department_id', $this->departmentId)->orderBy('name')->get();
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
