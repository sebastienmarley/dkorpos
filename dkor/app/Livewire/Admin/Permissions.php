<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Permissions extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $label = '';

    public string $description = '';

    public function openCreate(): void
    {
        $this->authorize('permissions.manage');

        $this->editingId = null;
        $this->reset(['name', 'label', 'description']);
        $this->showModal = true;
    }

    public function openEdit(Permission $permission): void
    {
        $this->authorize('permissions.manage');

        $this->editingId = $permission->id;
        $this->name = $permission->name;
        $this->label = (string) $permission->label;
        $this->description = (string) $permission->description;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('permissions.manage');

        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        if (! $this->editingId) {
            $rules['name'] = ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', 'unique:permissions,name'];
        }

        $validated = $this->validate($rules, [
            'name.regex' => __('Utilisez le format groupe.action (ex. : products.update).'),
        ]);

        $data = [
            'label' => $validated['label'],
            'description' => filled($validated['description']) ? $validated['description'] : null,
        ];

        if ($this->editingId) {
            Permission::findOrFail($this->editingId)->update($data);
            Flux::toast(text: __('Permission mise à jour.'), variant: 'success');
        } else {
            Permission::create($data + ['name' => $validated['name'], 'guard_name' => 'web']);
            Flux::toast(text: __('Permission créée.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'label', 'description']);
    }

    public function render(): View
    {
        return view('livewire.admin.permissions', [
            'groups' => Permission::query()->with('roles')->orderBy('name')->get()
                ->groupBy(fn (Permission $permission) => $permission->group()),
        ])->layout('layouts.app', ['title' => __('Permissions')]);
    }
}
