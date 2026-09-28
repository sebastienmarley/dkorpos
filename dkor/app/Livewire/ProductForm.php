<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
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

    #[On('open-product-create')]
    public function openCreate(): void
    {
        $this->reset(['supplierId', 'model', 'cleanModel', 'cost']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function updatedModel(string $value): void
    {
        $this->cleanModel = UniqueCleanProductModel::clean($value);
    }

    public function save(): void
    {
        $this->cost = $this->normalizeCost($this->cost);
        $this->cleanModel = UniqueCleanProductModel::clean($this->model);

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => ['required', 'string', 'max:255', new UniqueCleanProductModel($this->supplierId)],
            'cost' => ['required', 'numeric', 'min:0'],
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

    private function normalizeCost(string $value): string
    {
        return number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::where('is_active', true)->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.product-form');
    }
}
