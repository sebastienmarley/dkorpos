<?php

namespace App\Livewire\Receptions;

use App\Models\Reception;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Reception $reception;

    public function mount(Reception $reception): void
    {
        $this->reception = $reception->load(['supplier', 'receiver', 'lines.product', 'lines.orderLine.order']);
    }

    public function render(): View
    {
        return view('livewire.receptions.show')
            ->layout('layouts.app', ['title' => $this->reception->number]);
    }
}
