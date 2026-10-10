<?php

namespace App\Livewire\Parts;

use App\Enums\SupplierType;
use App\Models\Part;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Show extends Component
{
    public Part $part;

    public string $supplierId = '';

    public string $model = '';

    public string $description = '';

    public string $lastCost = '';

    public string $productSearch = '';

    public function mount(Part $part): void
    {
        $this->part = $part;
        $this->fillFromModel();
    }

    public function save(): void
    {
        $this->authorize('parts.edit');

        $this->lastCost = number_format((float) str_replace(',', '.', $this->lastCost), 2, '.', '');

        $this->validate([
            'supplierId' => ['required', 'exists:suppliers,id'],
            'model' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $duplicate = Part::query()
                        ->where('supplier_id', $this->supplierId)
                        ->where('clean_model', UniqueCleanProductModel::clean((string) $value))
                        ->whereKeyNot($this->part->id)
                        ->exists();

                    if ($duplicate) {
                        $fail(__('Une pièce avec un modèle similaire existe déjà chez ce fournisseur.'));
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
            'lastCost' => ['required', 'numeric', 'min:0'],
        ]);

        $this->part->update([
            'supplier_id' => $this->supplierId,
            'model' => trim($this->model),
            'description' => trim($this->description),
            'last_cost' => $this->lastCost,
        ]);

        $this->part->refresh();
        Flux::toast(text: __('Pièce sauvegardée.'), variant: 'success');
    }

    public function attachProduct(int $productId): void
    {
        $this->authorize('parts.edit');

        $this->part->products()->syncWithoutDetaching([Product::findOrFail($productId)->id]);
        $this->reset('productSearch');
    }

    public function detachProduct(int $productId): void
    {
        $this->authorize('parts.edit');

        $this->part->products()->detach($productId);
    }

    /**
     * Produits pas encore liés dont le modèle ou le modèle fournisseur contient le terme (2 caractères minimum).
     *
     * @return Collection<int, Product>
     */
    public function getProductResults(): Collection
    {
        $term = trim($this->productSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        return Product::query()
            ->with('supplier')
            ->whereNotIn('id', $this->part->products()->pluck('products.id'))
            ->where(fn (Builder $query) => $query
                ->where('model', 'like', '%'.$term.'%')
                ->orWhere('supplier_model', 'like', '%'.$term.'%'))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return Supplier::query()
            ->where('type', SupplierType::Product)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereKey($this->part->supplier_id))
            ->orderBy('name')
            ->get();
    }

    private function fillFromModel(): void
    {
        $this->supplierId = (string) $this->part->supplier_id;
        $this->model = $this->part->model;
        $this->description = $this->part->description;
        $this->lastCost = number_format($this->part->last_cost, 2, '.', '');
    }

    public function render(): View
    {
        return view('livewire.parts.show', [
            'products' => $this->part->products()->with('supplier')->orderBy('model')->get(),
        ])->layout('layouts.app', ['title' => $this->part->model]);
    }
}
