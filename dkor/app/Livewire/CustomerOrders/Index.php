<?php

namespace App\Livewire\CustomerOrders;

use App\Concerns\SearchesCustomers;
use App\Enums\CustomerOrderStatus;
use App\Models\customer;
use App\Models\CustomerOrder;
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

    public bool $showCreate = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('customer_orders.create');

        $this->customerSearch = '';
        $this->showCreate = true;
    }

    /**
     * Crée une commande pour le client choisi, avec l'utilisateur connecté comme vendeur à 100 %.
     */
    public function create(int $customerId): void
    {
        $this->authorize('customer_orders.create');

        $customer = customer::findOrFail($customerId);

        $order = DB::transaction(function () use ($customer): CustomerOrder {
            $order = CustomerOrder::create([
                'customer_id' => $customer->id,
                'created_by' => auth()->id(),
            ]);

            $order->syncSalespeople([['user_id' => (int) auth()->id(), 'percent' => 100]]);

            return $order;
        });

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

        $orders = CustomerOrder::query()
            ->with(['customer', 'salespeople'])
            ->when(filled($this->statusFilter), fn ($query) => $query->where('status', $this->statusFilter))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->whereHas('customer', fn ($c) => $c
                        ->where('firstname', 'like', '%'.$term.'%')
                        ->orWhere('lastname', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhere('cellphone', 'like', '%'.$term.'%'))
                        ->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term));
                });
            })
            ->latest('id')
            ->paginate(10);

        return view('livewire.customer-orders.index', [
            'orders' => $orders,
            'statuses' => CustomerOrderStatus::cases(),
            'customerResults' => $this->getCustomerResults(),
        ])->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
