<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ReceptionLine;
use App\Models\SupplierOrderLine;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Movements extends Component
{
    use WithPagination;

    #[Url(as: 'product')]
    public string $productFilter = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public bool $showMove = false;

    public string $productSearch = '';

    public ?int $moveProductId = null;

    public string $fromStatus = 'in_stock';

    public string $toStatus = 'in_demo';

    public string $quantity = '1';

    public string $note = '';

    public function updatedProductFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function openMove(): void
    {
        $this->authorize('inventory.move');

        $this->reset('productSearch', 'moveProductId', 'note');
        $this->fromStatus = InventoryStatus::InStock->value;
        $this->toStatus = InventoryStatus::InDemo->value;
        $this->quantity = '1';
        $this->resetValidation();
        $this->showMove = true;
    }

    public function selectProduct(int $productId): void
    {
        $this->authorize('inventory.move');

        $this->moveProductId = Product::findOrFail($productId)->id;
    }

    public function move(): void
    {
        $this->authorize('inventory.move');

        $movable = collect(InventoryStatus::cases())->filter->isManuallyMovable()->map->value->all();

        $this->validate([
            'moveProductId' => ['required', 'integer', 'exists:products,id'],
            'fromStatus' => ['required', Rule::in($movable)],
            'toStatus' => ['required', Rule::in($movable), 'different:fromStatus'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            InventoryMovement::record(
                (int) $this->moveProductId,
                InventoryStatus::from($this->fromStatus),
                InventoryStatus::from($this->toStatus),
                (int) $this->quantity,
                InventoryMovementType::Transfer,
                note: $this->note,
            );
        } catch (DomainException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->showMove = false;
        $this->resetPage();

        Flux::toast(text: __('Mouvement enregistré.'), variant: 'success');
    }

    /** @return Collection<int, Product> */
    private function searchProducts(): Collection
    {
        return Product::query()
            ->when(filled($this->productSearch), fn ($query) => $query->where(function ($q) {
                $q->where('model', 'like', '%'.$this->productSearch.'%')
                    ->orWhere('supplier_model', 'like', '%'.$this->productSearch.'%');
            }))
            ->orderBy('model')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        $movements = InventoryMovement::query()
            ->with([
                'product', 'user',
                'reference' => fn (Relation $relation) => $relation instanceof MorphTo
                    ? $relation->morphWith([
                        SupplierOrderLine::class => ['order'],
                        ReceptionLine::class => ['reception'],
                    ])
                    : $relation,
            ])
            ->when(filled($this->productFilter), fn ($query) => $query->where('product_id', $this->productFilter))
            ->when(filled($this->statusFilter), fn ($query) => $query->where(function ($q) {
                $q->where('from_status', $this->statusFilter)->orWhere('to_status', $this->statusFilter);
            }))
            ->when(filled($this->typeFilter), fn ($query) => $query->where('type', $this->typeFilter))
            ->latest('id')
            ->paginate(15);

        $selectedProduct = $this->moveProductId ? Product::with('inventoryStock')->find($this->moveProductId) : null;

        return view('livewire.inventory.movements', [
            'movements' => $movements,
            'statuses' => InventoryStatus::cases(),
            'movableStatuses' => collect(InventoryStatus::cases())->filter->isManuallyMovable()->values(),
            'types' => InventoryMovementType::cases(),
            'filteredProduct' => filled($this->productFilter) ? Product::find($this->productFilter) : null,
            'selectedProduct' => $selectedProduct,
            'productResults' => $this->showMove && ! $this->moveProductId ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Journal d\'inventaire')]);
    }
}
