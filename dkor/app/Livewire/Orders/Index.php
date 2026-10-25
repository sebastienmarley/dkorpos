<?php

namespace App\Livewire\Orders;

use App\Enums\StoreType;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    /** Magasin de destination affiché (vide : tous), offert seulement avec stores.all (ADR 0013). */
    public string $storeFilter = '';

    public bool $showCreate = false;

    /** Magasin de destination de la nouvelle commande : celui de l'usager, ou un autre au choix avec stores.all. */
    public string $newStoreId = '';

    public string $newType = 'product';

    public string $newSupplierId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStoreFilter(): void
    {
        $this->resetPage();
    }

    public function updatedNewType(): void
    {
        $this->newSupplierId = '';
    }

    public function openCreate(): void
    {
        $this->authorize('supplier_orders.create');

        $this->reset('newSupplierId');
        $this->newType = SupplierType::Product->value;
        $this->newStoreId = $this->defaultNewStoreId();
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create(): void
    {
        $this->authorize('supplier_orders.create');

        if ($this->newStoreId === '') {
            $this->newStoreId = $this->defaultNewStoreId();
        }

        $this->validate([
            'newType' => ['required', Rule::in([SupplierType::Product->value, SupplierType::Service->value])],
            'newSupplierId' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')
                    ->where('type', $this->newType)
                    ->where('is_active', true)
                    ->where('orderable', true),
            ],
            'newStoreId' => ['required', 'integer', Rule::in($this->getStores()->modelKeys())],
        ]);

        if (SupplierOrder::hasDraftFor($this->newSupplierId, (int) $this->newStoreId)) {
            $this->addError('newSupplierId', __('Une commande en brouillon existe déjà pour ce fournisseur et ce magasin.'));

            return;
        }

        $order = SupplierOrder::create([
            'store_id' => (int) $this->newStoreId,
            'type' => $this->newType,
            'supplier_id' => $this->newSupplierId,
            'created_by' => auth()->id(),
        ]);

        $this->redirectRoute('supplier-orders.show', $order, navigate: true);
    }

    /**
     * Magasins de destination offerts : les magasins physiques actifs accessibles à l'usager.
     *
     * @return Collection<int, Store>
     */
    public function getStores(): Collection
    {
        return Store::query()->accessibleTo(auth()->user())->where('type', StoreType::Physical)->where('is_active', true)->orderBy('name')->get();
    }

    /** Magasin proposé : celui du filtre, sinon celui de l'usager (ADR 0012), s'il est offert. */
    private function defaultNewStoreId(): string
    {
        $stores = $this->getStores();

        foreach ([$this->storeFilter, Store::defaultIdFor(auth()->user())] as $candidate) {
            if (filled($candidate) && $stores->contains('id', (int) $candidate)) {
                return (string) $candidate;
            }
        }

        return (string) ($stores->first()->id ?? '');
    }

    /** @return array<int, SupplierType> */
    public function getOrderTypes(): array
    {
        return [SupplierType::Product, SupplierType::Service];
    }

    public function render(): View
    {
        $user = auth()->user();

        $orders = SupplierOrder::query()
            ->accessibleTo($user)
            ->with(['supplier', 'lines', 'store'])
            ->when($this->storeFilter !== '' && $user->hasAllStores(), fn ($query) => $query->where('store_id', (int) $this->storeFilter))
            ->when(filled($this->typeFilter), fn ($query) => $query->where('type', $this->typeFilter))
            ->when(filled($this->statusFilter), fn ($query) => $query->where('status', $this->statusFilter))
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('number', 'like', '%'.$this->search.'%')
                        ->orWhere('quote_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('id')
            ->paginate(10);

        return view('livewire.orders.index', [
            'orders' => $orders,
            'statuses' => SupplierOrderStatus::cases(),
            'stores' => $this->getStores(),
            'creatableSuppliers' => Supplier::query()
                ->where('type', $this->newType)
                ->where('is_active', true)
                ->where('orderable', true)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Commandes fournisseurs')]);
    }
}
