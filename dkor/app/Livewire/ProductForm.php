<?php

namespace App\Livewire;

use App\Actions\CreateProductFromPriceListItem;
use App\Enums\SupplierType;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductForm extends Component
{
    public bool $showModal = false;

    public string $supplierId = '';

    public string $model = '';

    public string $cleanModel = '';

    public string $cost = '';

    public string $catalogSearch = '';

    #[On('open-product-create')]
    public function openCreate(): void
    {
        $this->authorize('products.create');

        $this->reset(['supplierId', 'model', 'cleanModel', 'cost', 'catalogSearch']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function updatedModel(string $value): void
    {
        $this->cleanModel = UniqueCleanProductModel::clean($value);
    }

    public function save(): void
    {
        $this->authorize('products.create');

        $this->cost = $this->normalizeCost($this->cost);
        $this->cleanModel = UniqueCleanProductModel::clean($this->model);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255', new UniqueCleanProductModel($this->supplierId), new UniqueCleanProductModel($this->supplierId, null, 'supplier_clean_model')],
            'cost' => ['required', 'numeric', 'min:0.01'],
        ]);

        $product = Product::create([
            'supplier_id' => $this->supplierId,
            'model' => $this->model,
            'clean_model' => $this->cleanModel,
            'cost' => $this->cost,
        ]);

        $this->showModal = false;
        $this->reset(['supplierId', 'model', 'cleanModel', 'cost']);

        $this->dispatch('product-saved', id: $product->id);
    }

    public function updatedSupplierId(): void
    {
        $this->catalogSearch = '';
    }

    /** @return Collection<int, PriceListItem> */
    public function getCatalogResults(): Collection
    {
        $term = trim($this->catalogSearch);

        if (! filled($this->supplierId) || mb_strlen($term) < 2) {
            return new Collection;
        }

        return PriceListItem::query()
            ->inActiveLists((int) $this->supplierId)
            ->where(fn ($query) => $query
                ->where('model', 'like', '%'.$term.'%')
                ->orWhere('collection', 'like', '%'.$term.'%'))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    public function selectFromPriceList(int $itemId, CreateProductFromPriceListItem $createProduct): void
    {
        $this->authorize('products.create');

        $this->validate(['supplierId' => ['required', 'exists:suppliers,id']]);

        $item = PriceListItem::query()->inActiveLists((int) $this->supplierId)->findOrFail($itemId);

        $product = $createProduct->handle($item);

        Flux::toast(
            text: $product->wasRecentlyCreated ? __('Produit créé à partir de la liste de prix.') : __('Ce produit existe déjà chez ce fournisseur.'),
            variant: $product->wasRecentlyCreated ? 'success' : 'warning',
        );

        $this->showModal = false;
        $this->reset(['supplierId', 'model', 'cleanModel', 'cost', 'catalogSearch']);

        $this->dispatch('product-saved', id: $product->id);
    }

    private function normalizeCost(string $value): string
    {
        return number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('type', SupplierType::Product)->where('is_active', true)->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.product-form');
    }
}
