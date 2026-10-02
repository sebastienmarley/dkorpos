<?php

namespace App\Livewire\Catalog;

use App\Models\Color;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Colors extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $hexCode = '';

    public function openCreate(): void
    {
        $this->authorize('colors.create');

        $this->editingId = null;
        $this->reset(['name', 'hexCode']);
        $this->showModal = true;
    }

    public function openEdit(Color $color): void
    {
        $this->authorize('colors.edit');

        $this->editingId = $color->id;
        $this->name = $color->name;
        $this->hexCode = $color->hex_code ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'colors.edit' : 'colors.create');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'hexCode' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $data = [
            'name' => $this->name,
            'hex_code' => filled($this->hexCode) ? $this->hexCode : null,
        ];

        if ($this->editingId) {
            Color::findOrFail($this->editingId)->update($data);
            Flux::toast(text: __('Couleur mise à jour.'), variant: 'success');
        } else {
            Color::create($data);
            Flux::toast(text: __('Couleur créée.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'hexCode']);
    }

    public function render(): View
    {
        return view('livewire.catalog.colors', [
            'colors' => Color::withCount('products')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Couleurs')]);
    }
}
