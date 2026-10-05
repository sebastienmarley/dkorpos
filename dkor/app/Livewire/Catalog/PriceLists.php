<?php

namespace App\Livewire\Catalog;

use App\Enums\SupplierType;
use App\Models\PriceList;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PriceLists extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public string $supplierId = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public function openCreate(): void
    {
        $this->authorize('price_lists.create');

        $this->resetErrorBag();
        $this->supplierId = '';
        $this->startsOn = today()->toDateString();
        $this->endsOn = PriceList::defaultEndDate()->toDateString();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('price_lists.create');

        $this->validate([
            'supplierId' => ['required', Rule::exists('suppliers', 'id')->where('type', SupplierType::Product->value)],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
        ]);

        if (PriceList::supplierHasActiveListOverlapping((int) $this->supplierId, $this->startsOn, $this->endsOn)) {
            $this->addError('supplierId', __('Ce fournisseur a déjà une liste de prix active pendant cette période.'));

            return;
        }

        PriceList::create([
            'supplier_id' => (int) $this->supplierId,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
        ]);

        $this->showModal = false;
        Flux::toast(text: __('Liste de prix créée.'), variant: 'success');
    }

    public function render(): View
    {
        $priceLists = filled($this->search)
            ? PriceList::query()
                ->active()
                ->with('supplier')
                ->whereHas('supplier', fn ($query) => $query
                    ->where('type', SupplierType::Product)
                    ->where('name', 'like', '%'.$this->search.'%'))
                ->orderBy('starts_on')
                ->get()
                ->sortBy(fn (PriceList $list) => $list->supplier->name)
                ->values()
            : collect();

        return view('livewire.catalog.price-lists', [
            'priceLists' => $priceLists,
            'creatableSuppliers' => Supplier::query()
                ->where('type', SupplierType::Product)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => __('Listes de prix')]);
    }
}
