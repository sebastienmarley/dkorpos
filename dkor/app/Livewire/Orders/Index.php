<?php

namespace App\Livewire\Orders;

use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public bool $showCreate = false;

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

    public function updatedNewType(): void
    {
        $this->newSupplierId = '';
    }

    public function openCreate(): void
    {
        $this->authorize('supplier_orders.create');

        $this->reset('newSupplierId');
        $this->newType = SupplierType::Product->value;
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create(): void
    {
        $this->authorize('supplier_orders.create');

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
        ]);

        if (SupplierOrder::hasDraftFor($this->newSupplierId)) {
            $this->addError('newSupplierId', __('Une commande en brouillon existe déjà pour ce fournisseur.'));

            return;
        }

        $order = SupplierOrder::create([
            'type' => $this->newType,
            'supplier_id' => $this->newSupplierId,
            'created_by' => auth()->id(),
        ]);

        $this->redirectRoute('supplier-orders.show', $order, navigate: true);
    }

    /** @return array<int, SupplierType> */
    public function getOrderTypes(): array
    {
        return [SupplierType::Product, SupplierType::Service];
    }

    public function render(): View
    {
        $orders = SupplierOrder::query()
            ->with(['supplier', 'lines'])
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
            'creatableSuppliers' => Supplier::query()
                ->where('type', $this->newType)
                ->where('is_active', true)
                ->where('orderable', true)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => __('Commandes fournisseurs')]);
    }
}
