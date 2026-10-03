<?php

namespace App\Livewire\Receptions;

use App\Models\Reception;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $receptions = Reception::query()
            ->with(['supplier', 'receiver', 'lines'])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('number', 'like', '%'.$this->search.'%')
                        ->orWhere('reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('id')
            ->paginate(10);

        return view('livewire.receptions.index', ['receptions' => $receptions])
            ->layout('layouts.app', ['title' => __('Réceptions')]);
    }
}
