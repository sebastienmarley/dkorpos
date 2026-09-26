<?php

namespace App\Livewire\Customers;

use App\Models\customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    #[On('customer-saved')]
    public function refresh(): void {}

    public function render(): View
    {
        $customers = blank($this->search)
            ? collect()
            : customer::query()
                ->where(function ($q) {
                    $q->where('firstname', 'like', '%'.$this->search.'%')
                        ->orWhere('lastname', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%')
                        ->orWhere('cellphone', 'like', '%'.$this->search.'%');
                })
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get();

        return view('livewire.customers.index', [
            'customers' => $customers,
        ])->layout('layouts.app', ['title' => __('Clients')]);
    }
}
