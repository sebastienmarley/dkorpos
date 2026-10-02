<?php

use App\Livewire\Admin\Permissions;
use App\Models\Permission;
use App\Models\User;
use Livewire\Livewire;

it('refuse l\'accès sans la permission permissions.manage', function () {
    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->get(route('admin.permissions'))->assertForbidden();
});

it('affiche les permissions groupées à un administrateur', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->get(route('admin.permissions'))->assertOk()->assertSee('users.create');
});

it('crée une permission au format groupe.action', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Permissions::class)
        ->call('openCreate')
        ->set('name', 'products.update')
        ->set('label', 'Modifier un produit')
        ->call('save')
        ->assertHasNoErrors();

    expect(Permission::where('name', 'products.update')->first()->label)->toBe('Modifier un produit');
});

it('refuse une clé mal formée ou déjà utilisée', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Permissions::class)
        ->call('openCreate')
        ->set('name', 'Produits Modifier')
        ->set('label', 'X')
        ->call('save')
        ->assertHasErrors(['name'])
        ->set('name', 'users.create')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('modifie le libellé sans toucher à la clé', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $permission = Permission::findByName('users.view');

    Livewire::test(Permissions::class)
        ->call('openEdit', $permission)
        ->set('name', 'hack.key')
        ->set('label', 'Consulter les employés')
        ->call('save')
        ->assertHasNoErrors();

    $permission->refresh();

    expect($permission->name)->toBe('users.view')->and($permission->label)->toBe('Consulter les employés');
});
