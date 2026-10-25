<?php

namespace App\Livewire\Inventory;

use App\Actions\AdjustInventory;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryStatus;
use App\Enums\StoreType;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Store;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Ajustements d'inventaire (issue #52, ADR 0011) : perte, vol, usage magasin, correction de comptage et stock
 * initial, avec leur écriture au grand livre. Réservé à inventory.adjust.
 */
class Adjustments extends Component
{
    use WithPagination;

    public bool $showAdjust = false;

    public string $productSearch = '';

    public ?int $adjustProductId = null;

    public string $reason = 'breakage';

    public string $direction = 'exit';

    public string $status = 'in_stock';

    public string $quantity = '1';

    public string $note = '';

    /** Magasin dont le stock est ajusté (ADR 0012), par défaut celui de l'usager. */
    public string $storeId = '';

    public function mount(): void
    {
        $this->authorize('inventory.adjust');
    }

    public function openAdjust(): void
    {
        $this->authorize('inventory.adjust');

        $this->reset('productSearch', 'adjustProductId', 'note');
        $this->reason = InventoryAdjustmentReason::Breakage->value;
        $this->direction = 'exit';
        $this->status = InventoryStatus::InStock->value;
        $this->quantity = '1';
        $this->resetValidation();
        $this->showAdjust = true;
        $this->storeId = (string) (Store::defaultIdFor(auth()->user()) ?? '');
    }

    public function selectProduct(int $productId): void
    {
        $this->authorize('inventory.adjust');

        $this->adjustProductId = Product::findOrFail($productId)->id;
    }

    /**
     * Garde le sens et l'état dans ce que permet le nouveau motif.
     */
    public function updatedReason(): void
    {
        $reason = InventoryAdjustmentReason::tryFrom($this->reason);

        if ($reason === null) {
            return;
        }

        if ($this->direction === 'entry' && ! $reason->allowsEntry()) {
            $this->direction = 'exit';
        } elseif ($this->direction === 'exit' && ! $reason->allowsExit()) {
            $this->direction = 'entry';
        }

        if (! in_array(InventoryStatus::tryFrom($this->status), $reason->statuses(), true)) {
            $this->status = $reason->statuses()[0]->value;
        }
    }

    public function adjust(AdjustInventory $adjustInventory): void
    {
        $this->authorize('inventory.adjust');

        $this->validate([
            'adjustProductId' => ['required', 'integer', 'exists:products,id'],
            'reason' => ['required', Rule::in(collect(InventoryAdjustmentReason::cases())->filter->isManual()->map->value->all())],
            'direction' => ['required', Rule::in(['entry', 'exit'])],
            'status' => ['required', Rule::enum(InventoryStatus::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'note' => ['required', 'string', 'max:255'],
            'storeId' => ['required', 'integer', Rule::exists('stores', 'id')->where('type', StoreType::Physical->value)->whereIn('id', Store::query()->accessibleTo(auth()->user())->pluck('id')->all())],
        ]);

        try {
            $adjustInventory->handle(
                auth()->user(),
                Product::findOrFail($this->adjustProductId),
                (int) $this->storeId,
                InventoryAdjustmentReason::from($this->reason),
                InventoryStatus::from($this->status),
                $this->direction === 'entry',
                (int) $this->quantity,
                $this->note,
            );
        } catch (DomainException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->showAdjust = false;
        $this->resetPage();

        Flux::toast(text: __('Ajustement enregistré.'), variant: 'success');
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
        $reason = InventoryAdjustmentReason::tryFrom($this->reason) ?? InventoryAdjustmentReason::Breakage;

        return view('livewire.inventory.adjustments', [
            'adjustments' => InventoryAdjustment::query()
                ->accessibleTo(auth()->user())
                ->with(['product', 'user', 'movement', 'ledgerEntry'])
                ->latest('id')
                ->paginate(15),
            'reasons' => collect(InventoryAdjustmentReason::cases())->filter->isManual()->values(),
            'selectedReason' => $reason,
            'reasonStatuses' => $reason->statuses(),
            'selectedProduct' => $this->adjustProductId ? Product::with('inventoryStocks')->find($this->adjustProductId) : null,
            'stores' => Store::query()->accessibleTo(auth()->user())->where('type', StoreType::Physical)->orderBy('name')->get(),
            'productResults' => $this->showAdjust && ! $this->adjustProductId ? $this->searchProducts() : collect(),
        ])->layout('layouts.app', ['title' => __('Ajustements d\'inventaire')]);
    }
}
