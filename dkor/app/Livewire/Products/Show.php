<?php

namespace App\Livewire\Products;

use App\Enums\SupplierType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Department;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use Closure;
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

    public string $imap = '';

    public string $newUpc = '';

    public string $collection = '';

    public string $description = '';

    public string $length = '';

    public string $width = '';

    public string $height = '';

    public string $weight = '';

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
        $this->authorize('products.edit');

        $this->cost = $this->normalizeCost($this->cost);
        $this->cleanModel = UniqueCleanProductModel::clean($this->model);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255', new UniqueCleanProductModel($this->supplierId, $this->product->id), new UniqueCleanProductModel($this->supplierId, $this->product->id, 'supplier_clean_model')],
            'supplierModel' => [
                'required', 'string', 'max:255',
                new UniqueCleanProductModel($this->supplierId, $this->product->id, 'supplier_clean_model'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== $this->product->supplier_model && $this->product->isInPriceList()) {
                        $fail(__('Le modèle fournisseur ne peut pas être modifié : le produit est dans une liste de prix.'));
                    }

                    if (filled($value) && UniqueCleanProductModel::clean($value) !== $this->cleanModel) {
                        $fail(__('Le modèle fournisseur doit correspondre au modèle une fois nettoyé.'));
                    }
                },
            ],
            'departmentId' => ['nullable', 'exists:departments,id'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'isDiscontinued' => ['boolean'],
            'isNonOrderable' => ['boolean'],
            'cost' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->product->fill([
            'supplier_id' => $this->supplierId,
            'model' => $this->model,
            'clean_model' => $this->cleanModel,
            'supplier_model' => $this->supplierModel,
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
        $this->authorize('products.edit');

        $this->validate([
            'colorId' => ['nullable', 'exists:colors,id'],
            'imap' => ['nullable', 'numeric', 'min:0'],
            'collection' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->product->fill([
            'color_id' => filled($this->colorId) ? $this->colorId : null,
            'imap' => filled($this->imap) ? $this->imap : null,
            'collection' => filled($this->collection) ? $this->collection : null,
            'description' => filled($this->description) ? $this->description : null,
            'length' => filled($this->length) ? $this->length : null,
            'width' => filled($this->width) ? $this->width : null,
            'height' => filled($this->height) ? $this->height : null,
            'weight' => filled($this->weight) ? $this->weight : null,
        ])->save();

        Flux::toast(text: __('Description sauvegardée.'), variant: 'success');
    }

    public function addUpc(): void
    {
        $this->authorize('products.edit');

        $this->newUpc = trim($this->newUpc);

        $this->validate(['newUpc' => ['required', 'regex:/^\d+$/', 'max:255']]);

        $this->product->upcs()->firstOrCreate(['upc' => $this->newUpc]);
        $this->reset('newUpc');
    }

    public function removeUpc(int $upcId): void
    {
        $this->authorize('products.edit');

        $this->product->upcs()->whereKey($upcId)->delete();
    }

    public function getSellingPrice(): float
    {
        $multiplier = Supplier::find($this->supplierId)->price_multiplier ?? 1.0;

        return Product::roundSellingPrice((float) $this->cost * $multiplier);
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('type', SupplierType::Product)->where('is_active', true)->orderBy('name')->get();
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
        $this->imap = $this->product->imap !== null ? (string) $this->product->imap : '';
        $this->collection = $this->product->collection ?? '';
        $this->description = $this->product->description ?? '';
        $this->length = $this->product->length !== null ? (string) $this->product->length : '';
        $this->width = $this->product->width !== null ? (string) $this->product->width : '';
        $this->height = $this->product->height !== null ? (string) $this->product->height : '';
        $this->weight = $this->product->weight !== null ? (string) $this->product->weight : '';
    }

    public function render(): View
    {
        return view('livewire.products.show', [
            'supplierModelLocked' => $this->product->isInPriceList(), 'upcs' => $this->product->upcs()->orderBy('id')->get()])
            ->layout('layouts.app', ['title' => $this->product->model]);
    }
}
