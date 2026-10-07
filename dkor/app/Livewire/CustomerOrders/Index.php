<?php

namespace App\Livewire\CustomerOrders;

use App\Models\customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public bool $showCustomerModal = false;

    public ?int $selectedCustomerId = null;

    public string $customerSearch = '';

    public function openCustomerModal(): void
    {
        $this->resetCustomerSelection();
        $this->showCustomerModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $customer = customer::findOrFail($id);

        $this->selectedCustomerId = $customer->id;
        $this->customerSearch = $customer->firstname.' '.$customer->lastname;
        $this->showCustomerModal = false;
    }

    public function clearCustomer(): void
    {
        $this->resetCustomerSelection();
    }

    #[On('customer-saved')]
    public function onCustomerSaved(int $id): void
    {
        $this->selectCustomer($id);
    }

    private function resetCustomerSelection(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
    }

    public function render(): View
    {
        $customerResults = strlen($this->customerSearch) >= 4 && ! $this->selectedCustomerId
            ? customer::query()
                ->where(function ($q) {
                    $q->where('firstname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('lastname', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
                        ->orWhere('cellphone', 'like', '%'.$this->customerSearch.'%');
                })
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->limit(8)
                ->get()
            : collect();

        return view('livewire.customer-orders.index', [
            'customerResults' => $customerResults,
            'selectedCustomer' => $this->selectedCustomerId ? customer::find($this->selectedCustomerId) : null,
        ])->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
