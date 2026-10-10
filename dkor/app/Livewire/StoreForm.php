<?php

namespace App\Livewire;

use App\Enums\Province;
use App\Enums\StoreType;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class StoreForm extends Component
{
    public bool $showModal = false;

    public string $name = '';

    public string $type = 'physical';

    public string $province = 'QC';

    #[On('open-store-create')]
    public function openCreate(): void
    {
        $this->authorize('stores.create');

        $this->reset(['name', 'type', 'province']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('stores.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(StoreType::class)],
            'province' => ['required', Rule::enum(Province::class)],
        ]);

        $store = Store::create($validated + ['opening_hours' => Store::defaultOpeningHours()]);

        $this->showModal = false;
        $this->reset(['name', 'type', 'province']);

        $this->dispatch('store-saved', id: $store->id);
    }

    /** @return array<int, StoreType> */
    public function getStoreTypes(): array
    {
        return StoreType::cases();
    }

    /** @return array<int, Province> */
    public function getProvinces(): array
    {
        return Province::cases();
    }

    public function render(): View
    {
        return view('livewire.store-form');
    }
}
