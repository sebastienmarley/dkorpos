<?php

namespace App\Livewire\Stores;

use App\Models\InventoryLocation;
use App\Models\InventoryZone;
use App\Models\Store;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Onglet « Zones et emplacements » de la fiche d'un magasin (issue #96) : les zones de son entrepôt, qui servent à la
 * prise d'inventaire (ADR 0011), et dans chaque zone ses emplacements, au code unique dans le magasin. Une zone ou un
 * emplacement désactivé n'est plus proposé, mais reste affiché là où il est noté.
 */
class Locations extends Component
{
    public Store $store;

    public bool $showZoneModal = false;

    public ?int $editingZoneId = null;

    public string $zoneName = '';

    public string $zoneSortOrder = '0';

    public bool $showLocationModal = false;

    public ?int $editingLocationId = null;

    public string $locationZoneId = '';

    public string $locationCode = '';

    public string $locationDescription = '';

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function openZone(?int $zoneId = null): void
    {
        $this->authorize('inventory.supervise');

        $zone = $zoneId === null ? null : $this->store->inventoryZones()->findOrFail($zoneId);

        $this->editingZoneId = $zone?->id;
        $this->zoneName = $zone->name ?? '';
        $this->zoneSortOrder = (string) ($zone->sort_order ?? ((int) $this->store->inventoryZones()->max('sort_order') + 10));
        $this->resetValidation();
        $this->showZoneModal = true;
    }

    public function saveZone(): void
    {
        $this->authorize('inventory.supervise');

        $this->zoneName = trim($this->zoneName);

        $this->validate([
            'zoneName' => ['required', 'string', 'max:100', Rule::unique('inventory_zones', 'name')->where('store_id', $this->store->id)->ignore($this->editingZoneId)],
            'zoneSortOrder' => ['required', 'integer', 'min:0', 'max:99999'],
        ]);

        $this->store->inventoryZones()->updateOrCreate(['id' => $this->editingZoneId], ['name' => $this->zoneName, 'sort_order' => (int) $this->zoneSortOrder]);

        $this->showZoneModal = false;
        Flux::toast(text: __('Zone enregistrée.'), variant: 'success');
    }

    public function toggleZone(int $zoneId): void
    {
        $this->authorize('inventory.supervise');

        $zone = $this->store->inventoryZones()->findOrFail($zoneId);
        $zone->update(['is_active' => ! $zone->is_active]);
    }

    public function openLocation(?int $zoneId = null, ?int $locationId = null): void
    {
        $this->authorize('inventory.supervise');

        $location = $locationId === null ? null : InventoryLocation::query()->ofStore($this->store->id)->findOrFail($locationId);

        $this->editingLocationId = $location?->id;
        $this->locationZoneId = (string) ($location->inventory_zone_id ?? $zoneId ?? '');
        $this->locationCode = $location->code ?? '';
        $this->locationDescription = $location->description ?? '';
        $this->resetValidation();
        $this->showLocationModal = true;
    }

    public function saveLocation(): void
    {
        $this->authorize('inventory.supervise');

        $this->locationCode = mb_strtoupper(trim($this->locationCode));

        $this->validate([
            'locationZoneId' => ['required', 'integer', Rule::exists('inventory_zones', 'id')->where('store_id', $this->store->id)],
            'locationCode' => ['required', 'string', 'max:50', $this->uniqueInStore()],
            'locationDescription' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryLocation::query()->updateOrCreate(['id' => $this->editingLocationId], [
            'inventory_zone_id' => (int) $this->locationZoneId,
            'code' => $this->locationCode,
            'description' => filled($this->locationDescription) ? trim($this->locationDescription) : null,
        ]);

        $this->showLocationModal = false;
        Flux::toast(text: __('Emplacement enregistré.'), variant: 'success');
    }

    public function toggleLocation(int $locationId): void
    {
        $this->authorize('inventory.supervise');

        $location = InventoryLocation::query()->ofStore($this->store->id)->findOrFail($locationId);
        $location->update(['is_active' => ! $location->is_active]);
    }

    /** @return Collection<int, InventoryZone> */
    public function getZones(): Collection
    {
        return $this->store->inventoryZones()->with('locations')->ordered()->get();
    }

    /** Le code d'un emplacement est unique dans le magasin, toutes zones confondues (sans tenir compte de la casse). */
    private function uniqueInStore(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $taken = InventoryLocation::query()
                ->ofStore($this->store->id)
                ->whereRaw('lower(code) = ?', [mb_strtolower((string) $value)])
                ->when($this->editingLocationId !== null, fn ($query) => $query->whereKeyNot($this->editingLocationId))
                ->exists();

            if ($taken) {
                $fail(__('Ce code est déjà utilisé dans ce magasin.'));
            }
        };
    }

    public function render(): View
    {
        return view('livewire.stores.locations', ['zones' => $this->getZones()]);
    }
}
