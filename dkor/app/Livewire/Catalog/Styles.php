<?php

namespace App\Livewire\Catalog;

use App\Models\Style;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Styles extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('styles.create');

        $this->resetErrorBag();
        $this->reset(['editingId', 'name']);
        $this->showModal = true;
    }

    public function openEdit(Style $style): void
    {
        $this->authorize('styles.edit');

        $this->resetErrorBag();
        $this->editingId = $style->id;
        $this->name = $style->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'styles.edit' : 'styles.create');

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('styles', 'name')->ignore($this->editingId)],
        ]);

        if ($this->editingId) {
            Style::findOrFail($this->editingId)->update(['name' => $this->name]);
            Flux::toast(text: __('Style mis à jour.'), variant: 'success');
        } else {
            Style::create(['name' => $this->name]);
            Flux::toast(text: __('Style créé.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name']);
    }

    public function render(): View
    {
        return view('livewire.catalog.styles', [
            'styles' => Style::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
