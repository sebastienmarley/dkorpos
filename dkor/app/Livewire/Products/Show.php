<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\Color;
use App\Models\Department;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Show extends Component
{
    public Product $product;

    public string $activeTab = 'general';

    // Général
    public string $supplierId = '';

    public string $model = '';

    public string $cleanModel = '';

    public string $supplierModel = '';

    public string $departmentId = '';

    public string $categoryId = '';

    public bool $isDiscontinued = false;

    public bool $isNonOrderable = false;

    public string $cost = '';

    // Description
    public string $colorId = '';

    public string $size = '';

    public string $description = '';

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->fillFromModel();
    }

    public function updatedModel(string $value): void
    {
        $this->cleanModel = UniqueCleanProductModel::clean($value);
    }

    public function saveGeneral(): void
    {
        $this->cost = $this->normalizeCost($this->cost);
        $this->cleanModel = UniqueCleanProductModel::clean($this->model);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255', new UniqueCleanProductModel($this->supplierId, $this->product->id)],
            'supplierModel' => ['nullable', 'string', 'max:255'],
            'departmentId' => ['nullable', 'exists:departments,id'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'isDiscontinued' => ['boolean'],
            'isNonOrderable' => ['boolean'],
            'cost' => ['required', 'numeric', 'min:0'],
        ]);

        $this->product->fill([
            'supplier_id' => $this->supplierId,
            'model' => $this->model,
            'clean_model' => $this->cleanModel,
            'supplier_model' => filled($this->supplierModel) ? $this->supplierModel : null,
            'department_id' => filled($this->departmentId) ? $this->departmentId : null,
            'category_id' => filled($this->categoryId) ? $this->categoryId : null,
            'is_discontinued' => $this->isDiscontinued,
            'is_non_orderable' => $this->isNonOrderable,
            'cost' => $this->cost,
        ])->save();

        $this->product->refresh();
        Flux::toast(text: __('Général sauvegardé.'), variant: 'success');
    }

    public function saveDescription(): void
    {
        $this->validate([
            'colorId' => ['nullable', 'exists:colors,id'],
            'size' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $this->product->fill([
            'color_id' => filled($this->colorId) ? $this->colorId : null,
            'size' => filled($this->size) ? $this->size : null,
            'description' => filled($this->description) ? $this->description : null,
        ])->save();

        Flux::toast(text: __('Description sauvegardée.'), variant: 'success');
    }

    public function getSellingPrice(): float
    {
        $multiplier = Supplier::find($this->supplierId)->price_multiplier ?? 1.0;

        return (float) $this->cost * $multiplier;
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return Category::when(filled($this->departmentId), fn ($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Color> */
    public function getColors(): Collection
    {
        return Color::orderBy('name')->get();
    }

    private function normalizeCost(string $value): string
    {
        return number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }

    private function fillFromModel(): void
    {
        $this->supplierId = (string) $this->product->supplier_id;
        $this->model = $this->product->model;
        $this->cleanModel = $this->product->clean_model;
        $this->supplierModel = $this->product->supplier_model ?? '';
        $this->departmentId = (string) ($this->product->department_id ?? '');
        $this->categoryId = (string) ($this->product->category_id ?? '');
        $this->isDiscontinued = $this->product->is_discontinued;
        $this->isNonOrderable = $this->product->is_non_orderable;
        $this->cost = (string) $this->product->cost;
        $this->colorId = (string) ($this->product->color_id ?? '');
        $this->size = $this->product->size ?? '';
        $this->description = $this->product->description ?? '';
    }

    public function render(): View
    {
        return view('livewire.products.show')
            ->layout('layouts.app', ['title' => $this->product->model]);
    }
}
