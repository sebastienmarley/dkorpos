<?php

namespace App\Livewire\Catalog;

use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Departments extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->reset('name');
        $this->showModal = true;
    }

    public function openEdit(Department $department): void
    {
        $this->editingId = $department->id;
        $this->name = $department->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255', 'unique:departments,name,'.($this->editingId ?? 'NULL')]]);

        if ($this->editingId) {
            Department::findOrFail($this->editingId)->update(['name' => $this->name]);
            Flux::toast(text: __('Département mis à jour.'), variant: 'success');
        } else {
            Department::create(['name' => $this->name]);
            Flux::toast(text: __('Département créé.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name']);
    }

    public function render(): View
    {
        return view('livewire.catalog.departments', [
            'departments' => Department::withCount('products')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Départements')]);
    }
}
