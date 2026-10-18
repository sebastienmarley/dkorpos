<?php

namespace App\Livewire\Catalog;

use App\Models\Category;
use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Arborescence Département > Catégorie de la page Classification.
 */
class Departments extends Component
{
    public bool $showDepartmentModal = false;

    public ?int $editingDepartmentId = null;

    public string $departmentName = '';

    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public string $categoryDepartmentId = '';

    public string $categoryName = '';

    public function openCreateDepartment(): void
    {
        $this->authorize('departments.create');

        $this->resetErrorBag();
        $this->reset(['editingDepartmentId', 'departmentName']);
        $this->showDepartmentModal = true;
    }

    public function openEditDepartment(Department $department): void
    {
        $this->authorize('departments.edit');

        $this->resetErrorBag();
        $this->editingDepartmentId = $department->id;
        $this->departmentName = $department->name;
        $this->showDepartmentModal = true;
    }

    public function saveDepartment(): void
    {
        $this->authorize($this->editingDepartmentId ? 'departments.edit' : 'departments.create');

        $this->validate([
            'departmentName' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($this->editingDepartmentId)],
        ]);

        if ($this->editingDepartmentId) {
            Department::findOrFail($this->editingDepartmentId)->update(['name' => $this->departmentName]);
            Flux::toast(text: __('Département mis à jour.'), variant: 'success');
        } else {
            Department::create(['name' => $this->departmentName]);
            Flux::toast(text: __('Département créé.'), variant: 'success');
        }

        $this->showDepartmentModal = false;
        $this->reset(['editingDepartmentId', 'departmentName']);
    }

    public function openCreateCategory(?int $departmentId = null): void
    {
        $this->authorize('categories.create');

        $this->resetErrorBag();
        $this->reset(['editingCategoryId', 'categoryName']);
        $this->categoryDepartmentId = (string) ($departmentId ?? '');
        $this->showCategoryModal = true;
    }

    public function openEditCategory(Category $category): void
    {
        $this->authorize('categories.edit');

        $this->resetErrorBag();
        $this->editingCategoryId = $category->id;
        $this->categoryDepartmentId = (string) $category->department_id;
        $this->categoryName = $category->name;
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->authorize($this->editingCategoryId ? 'categories.edit' : 'categories.create');

        $this->validate([
            'categoryDepartmentId' => ['required', 'exists:departments,id'],
            'categoryName' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')->where('department_id', $this->categoryDepartmentId)->ignore($this->editingCategoryId),
            ],
        ]);

        $data = [
            'department_id' => $this->categoryDepartmentId,
            'name' => $this->categoryName,
        ];

        if ($this->editingCategoryId) {
            Category::findOrFail($this->editingCategoryId)->update($data);
            Flux::toast(text: __('Catégorie mise à jour.'), variant: 'success');
        } else {
            Category::create($data);
            Flux::toast(text: __('Catégorie créée.'), variant: 'success');
        }

        $this->showCategoryModal = false;
        $this->reset(['editingCategoryId', 'categoryDepartmentId', 'categoryName']);
    }

    /** @return Collection<int, Department> */
    public function getDepartments(): Collection
    {
        return Department::with(['categories' => fn ($query) => $query->withCount('products')->orderBy('name')])
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.catalog.departments', [
            'departments' => $this->getDepartments(),
        ]);
    }
}
