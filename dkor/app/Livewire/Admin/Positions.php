<?php

namespace App\Livewire\Admin;

use App\Models\Position;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Positions extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public function openCreate(): void
    {
        $this->authorize('positions.manage');

        $this->editingId = null;
        $this->reset(['name']);
        $this->showModal = true;
    }

    public function openEdit(Position $position): void
    {
        $this->authorize('positions.manage');

        $this->editingId = $position->id;
        $this->name = $position->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('positions.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($this->editingId)],
        ]);

        if ($this->editingId) {
            Position::findOrFail($this->editingId)->update($validated);
            Flux::toast(text: __('Position mise à jour.'), variant: 'success');
        } else {
            Position::create($validated);
            Flux::toast(text: __('Position créée.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name']);
    }

    public function delete(Position $position): void
    {
        $this->authorize('positions.manage');

        if ($position->users()->exists()) {
            Flux::toast(text: __('Cette position est attribuée à des utilisateurs.'), variant: 'danger');

            return;
        }

        $position->delete();
        Flux::toast(text: __('Position supprimée.'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.positions', [
            'positions' => Position::withCount('users')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Positions')]);
    }
}
