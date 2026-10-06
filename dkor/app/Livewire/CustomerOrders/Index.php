<?php

namespace App\Livewire\CustomerOrders;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        return view('livewire.customer-orders.index')
            ->layout('layouts.app', ['title' => __('Commandes clients')]);
    }
}
