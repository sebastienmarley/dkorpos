<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryZone;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Zones permanentes de l'entrepôt pour le comptage du stock (ADR 0011). Une zone désactivée n'est plus proposée aux
 * prochaines prises d'inventaire.
 */
class Zones extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $sortOrder = '0';

    public function mount(): void
    {
        $this->authorize('inventory.supervise');
    }

    public function openCreate(): void
    {
        $this->authorize('inventory.supervise');

        $this->reset('editingId', 'name');
        $this->sortOrder = (string) ((int) InventoryZone::max('sort_order') + 10);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(InventoryZone $zone): void
    {
        $this->authorize('inventory.supervise');

        $this->editingId = $zone->id;
        $this->name = $zone->name;
        $this->sortOrder = (string) $zone->sort_order;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('inventory.supervise');

        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_zones', 'name')->ignore($this->editingId)],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:99999'],
        ]);

        InventoryZone::query()->updateOrCreate(['id' => $this->editingId], ['name' => $validated['name'], 'sort_order' => (int) $validated['sortOrder']]);

        $this->showModal = false;

        Flux::toast(text: __('Zone enregistrée.'), variant: 'success');
    }

    public function toggleActive(InventoryZone $zone): void
    {
        $this->authorize('inventory.supervise');

        $zone->update(['is_active' => ! $zone->is_active]);
    }

    public function render(): View
    {
        return view('livewire.inventory.zones', [
            'zones' => InventoryZone::query()->ordered()->get(),
        ])->layout('layouts.app', ['title' => __('Zones d\'inventaire')]);
    }
}
