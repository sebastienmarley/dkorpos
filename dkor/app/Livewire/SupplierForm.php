<?php

namespace App\Livewire;

use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class SupplierForm extends Component
{
    public bool $showModal = false;

    public string $type = 'product';

    public string $name = '';

    public string $phone = '';

    #[On('open-supplier-create')]
    public function openCreate(): void
    {
        $this->authorize('suppliers.create');

        $this->reset(['type', 'name', 'phone']);
        $this->type = 'product';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('suppliers.create');

        $validated = $this->validate([
            'type' => ['required', Rule::enum(SupplierType::class)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
        ]);

        $validated['phone'] = filled($validated['phone']) ? $validated['phone'] : null;

        $supplier = Supplier::create($validated);

        $this->showModal = false;
        $this->reset(['type', 'name', 'phone']);
        $this->type = 'product';

        $this->dispatch('supplier-saved', id: $supplier->id);
    }

    /** @return array<int, SupplierType> */
    public function getSupplierTypes(): array
    {
        return SupplierType::cases();
    }

    public function render(): View
    {
        return view('livewire.supplier-form');
    }
}
