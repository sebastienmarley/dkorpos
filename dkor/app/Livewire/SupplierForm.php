<?php

namespace App\Livewire;

use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SupplierForm extends Component
{
    public bool $showModal = false;

    public string $type = 'product';

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    #[On('open-supplier-create')]
    public function openCreate(): void
    {
        $this->reset(['type', 'name', 'address', 'phone']);
        $this->type = 'product';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:product,service'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
        ]);

        foreach (['address', 'phone'] as $field) {
            $validated[$field] = filled($validated[$field]) ? $validated[$field] : null;
        }

        $supplier = Supplier::create($validated);

        $this->showModal = false;
        $this->reset(['type', 'name', 'address', 'phone']);
        $this->type = 'product';

        $this->dispatch('supplier-saved', id: $supplier->id);
    }

    public function getSupplierTypes(): array
    {
        return SupplierType::cases();
    }

    public function render(): View
    {
        return view('livewire.supplier-form');
    }
}
