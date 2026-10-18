<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryCount;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Prises d'inventaire annuelles (issue #88) : la liste et le démarrage d'une prise.
 */
class Counts extends Component
{
    use WithPagination;

    public bool $showStart = false;

    public string $countedOn = '';

    public function openStart(): void
    {
        $this->authorize('inventory.adjust');

        $this->countedOn = today()->toDateString();
        $this->resetValidation();
        $this->showStart = true;
    }

    public function start(): void
    {
        $this->authorize('inventory.adjust');

        $this->validate(['countedOn' => ['required', 'date']]);

        try {
            $count = InventoryCount::start(Carbon::parse($this->countedOn), auth()->user());
        } catch (DomainException $e) {
            $this->addError('countedOn', $e->getMessage());

            return;
        }

        $this->redirectRoute('inventory.counts.show', $count, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.inventory.counts', [
            'counts' => InventoryCount::query()->with(['starter', 'finalizer'])->withCount('lines')->latest('id')->paginate(15),
            'inProgress' => InventoryCount::inProgress(),
        ])->layout('layouts.app', ['title' => __('Prises d\'inventaire')]);
    }
}
