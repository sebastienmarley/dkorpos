<?php

use App\Livewire\Admin\Roles;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

it('refuse l\'accès sans la permission roles.manage', function () {
    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->get(route('admin.roles'))->assertForbidden();
});

it('affiche la liste des rôles à un administrateur', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->get(route('admin.roles'))->assertOk()->assertSee('Directeur');
});

it('crée un rôle avec ses permissions de base', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Roles::class)
        ->call('openCreate')
        ->set('name', 'supervisor')
        ->set('label', 'Superviseur')
        ->set('level', 30)
        ->set('permissions', ['users.view', 'users.edit'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'supervisor')->firstOrFail();

    expect($role->level)->toBe(30);
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['users.edit', 'users.view']);
});

it('refuse un niveau supérieur à celui de l\'utilisateur', function () {
    $actor = User::factory()->withRole('admin')->create();
    $actor->role->update(['level' => 60]);
    $actor->givePermissionTo('roles.manage');

    $this->actingAs($actor->fresh());

    Livewire::test(Roles::class)
        ->call('openCreate')
        ->set('name', 'boss')
        ->set('label', 'Patron')
        ->set('level', 90)
        ->call('save')
        ->assertHasErrors(['level']);
});

it('ne modifie pas un rôle de niveau supérieur', function () {
    $actor = User::factory()->withRole('manager')->create();
    $actor->givePermissionTo('roles.manage');

    $this->actingAs($actor);

    Livewire::test(Roles::class)
        ->call('openEdit', Role::findByName('admin'))
        ->assertForbidden();
});

it('n\'accorde que les permissions que l\'utilisateur possède', function () {
    $actor = User::factory()->withRole('manager')->create();
    $actor->givePermissionTo('roles.manage');

    $this->actingAs($actor);

    Livewire::test(Roles::class)
        ->call('openCreate')
        ->set('name', 'sneaky')
        ->set('label', 'Sournois')
        ->set('level', 10)
        ->set('permissions', ['users.view', 'permissions.manage'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::findByName('sneaky')->permissions->pluck('name')->all())->toBe(['users.view']);
});

it('conserve les permissions du rôle que l\'utilisateur ne possède pas', function () {
    $actor = User::factory()->withRole('manager')->create();
    $actor->givePermissionTo('roles.manage');

    $role = Role::create(['name' => 'lead', 'label' => 'Chef', 'level' => 10, 'guard_name' => 'web']);
    $role->givePermissionTo(['users.view', 'permissions.manage']);

    $this->actingAs($actor);

    Livewire::test(Roles::class)
        ->call('openEdit', $role)
        ->set('permissions', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['permissions.manage']);
});

it('ne supprime pas un rôle attribué mais supprime un rôle libre', function () {
    User::factory()->create();
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Roles::class)->call('delete', Role::findByName('salesman'))->assertForbidden();

    $libre = Role::create(['name' => 'temp', 'label' => 'Temporaire', 'level' => 5, 'guard_name' => 'web']);

    Livewire::test(Roles::class)->call('delete', $libre)->assertHasNoErrors();

    expect(Role::where('name', 'temp')->exists())->toBeFalse();
});
