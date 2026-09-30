<?php

namespace App\Livewire\Catalog;

use App\Models\Category;
use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Categories extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $departmentId = '';

    public string $name = '';

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->reset(['departmentId', 'name']);
        $this->showModal = true;
    }

    public function openEdit(Category $category): void
    {
        $this->editingId = $category->id;
        $this->departmentId = (string) ($category->department_id ?? '');
        $this->name = $category->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'departmentId' => ['nullable', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $data = [
            'department_id' => filled($this->departmentId) ? $this->departmentId : null,
            'name' => $this->name,
        ];

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update($data);
            Flux::toast(text: __('Catégorie mise à jour.'), variant: 'success');
        } else {
            Category::create($data);
            Flux::toast(text: __('Catégorie créée.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'departmentId', 'name']);
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return Department::orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.catalog.categories', [
            'categories' => Category::with('department')->withCount('products')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Catégories')]);
    }
}
