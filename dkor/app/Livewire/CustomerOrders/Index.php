<?php

namespace App\Livewire\CustomerOrders;

use App\Concerns\SearchesCustomers;
use App\Enums\CustomerOrderStatus;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\Store;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use SearchesCustomers;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** Magasin affiché (vide : tous), offert seulement avec stores.all. */
    public string $storeFilter = '';

    public bool $showCreate = false;

    /** Magasin de la nouvelle commande : celui de l'usager, ou un autre au choix avec stores.all. */
    public string $createStoreId = '';

    public function updatedSearch(): void
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

    public function openCreate(): void
    {
        $this->authorize('customer_orders.create');

        $this->customerSearch = '';
        $this->createStoreId = (string) ($this->storeFilter !== '' ? $this->storeFilter : (auth()->user()->store_id ?? ''));
        $this->showCreate = true;
    }

    /**
     * Crée une commande pour le client choisi, dans le magasin de l'usager (ou celui choisi avec stores.all),
     * avec l'utilisateur connecté comme vendeur à 100 %.
     */
    public function create(int $customerId): void
    {
        $this->authorize('customer_orders.create');

        $customer = customer::findOrFail($customerId);
        $user = auth()->user();
        $storeId = $user->hasAllStores() && $this->createStoreId !== '' ? (int) $this->createStoreId : $user->store_id;

        abort_unless($storeId === null || $user->canActForStore($storeId), 403);

        try {
            $order = DB::transaction(function () use ($customer, $storeId): CustomerOrder {
                $order = CustomerOrder::create([
                    'customer_id' => $customer->id,
                    'store_id' => $storeId,
                    'created_by' => auth()->id(),
                ]);

                $order->syncSalespeople([['user_id' => (int) auth()->id(), 'percent' => 100]]);

                return $order;
            });
        } catch (DomainException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        $this->redirectRoute('customer-orders.show', $order, navigate: true);
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        if ($this->showCreate) {
            $this->create($id);
        }
    }

    public function render(): View
    {
        $term = trim($this->search);

        $user = auth()->user();

        $orders = CustomerOrder::query()
            ->accessibleTo($user)
            ->with(['customer', 'salespeople', 'store'])
            ->when($this->storeFilter !== '' && $user->hasAllStores(), fn ($query) => $query->where('store_id', (int) $this->storeFilter))
            ->when(filled($this->statusFilter), fn ($query) => $query->where('status', $this->statusFilter))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->whereHas('customer', fn ($c) => $c->matching($term))
                        ->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term));
                });
            })
            ->latest('id')
            ->paginate(10);

        return view('livewire.customer-orders.index', [
            'orders' => $orders,
            'statuses' => CustomerOrderStatus::cases(),
            'customerResults' => $this->getCustomerResults(),
            'stores' => $user->hasAllStores() ? Store::query()->orderBy('name')->get() : collect(),
        ])->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
