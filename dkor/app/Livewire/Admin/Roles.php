<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Roles extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $label = '';

    public int $level = 10;

    /** @var array<int, string> */
    public array $permissions = [];

    public function openCreate(): void
    {
        $this->authorize('create', Role::class);

        $this->editingId = null;
        $this->reset(['name', 'label', 'level', 'permissions']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(Role $role): void
    {
        $this->authorize('update', $role);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->label = (string) $role->label;
        $this->level = $role->level;
        $this->permissions = $role->permissions->pluck('name')->all();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $role = $this->editingId ? Role::findOrFail($this->editingId) : null;

        $role ? $this->authorize('update', $role) : $this->authorize('create', Role::class);

        $actor = Auth::user();

        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:0', 'max:'.$actor->roleLevel()],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];

        if (! $role) {
            $rules['name'] = ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'];
        }

        $validated = $this->validate($rules, [
            'level.max' => __('Le niveau ne peut pas dépasser le vôtre (:max).', ['max' => $actor->roleLevel()]),
        ]);

        $role ??= new Role(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->fill(['label' => $validated['label'], 'level' => $validated['level']])->save();

        $grantable = $actor->getAllPermissions()->pluck('name');
        $kept = $role->permissions->pluck('name')->reject(fn (string $name) => $grantable->contains($name));
        $selected = collect($this->permissions)->intersect($grantable);

        $role->syncPermissions($kept->merge($selected)->unique()->values()->all());

        Flux::toast(text: $this->editingId ? __('Rôle mis à jour.') : __('Rôle créé.'), variant: 'success');

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'label', 'level', 'permissions']);
    }

    public function delete(Role $role): void
    {
        $this->authorize('delete', $role);

        $role->delete();

        Flux::toast(text: __('Rôle supprimé.'), variant: 'success');
    }

    /**
     * Noms des permissions que l'utilisateur connecté peut accorder (celles qu'il possède).
     *
     * @return array<int, string>
     */
    public function grantablePermissions(): array
    {
        return Auth::user()->getAllPermissions()->pluck('name')->all();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('livewire.admin.roles', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('level')->orderBy('label')->get(),
            'permissionGroups' => Permission::query()->orderBy('name')->get()
                ->groupBy(fn (Permission $permission) => $permission->group()),
        ])->layout('layouts.app', ['title' => __('Rôles')]);
    }
}
