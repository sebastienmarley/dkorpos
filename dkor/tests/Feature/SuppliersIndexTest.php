<?php

use App\Livewire\Suppliers\Index;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Supplier::factory()->create(['name' => 'Actif Inc', 'is_active' => true]);
    Supplier::factory()->create(['name' => 'Dormant Ltée', 'is_active' => false]);
});

it('masque les fournisseurs inactifs par défaut', function () {
    Livewire::test(Index::class)
        ->assertSee('Actif Inc')
        ->assertDontSee('Dormant Ltée');
});

it('affiche les fournisseurs inactifs avec le filtre', function () {
    Livewire::test(Index::class)
        ->set('showInactive', true)
        ->assertSee('Actif Inc')
        ->assertSee('Dormant Ltée');
});

it('combine le filtre des inactifs avec la recherche', function () {
    Livewire::test(Index::class)
        ->set('showInactive', true)
        ->set('search', 'Dormant')
        ->assertSee('Dormant Ltée')
        ->assertDontSee('Actif Inc');
});

it('affiche 5 fournisseurs à la fois et pagine le reste', function () {
    Supplier::factory()->count(6)->sequence(fn ($s) => ['name' => 'Fournisseur '.str_pad($s->index, 2, '0', STR_PAD_LEFT), 'is_active' => true])->create();

    Livewire::test(Index::class)
        ->assertSee('Fournisseur 00')
        ->assertSee('Fournisseur 03')
        ->assertDontSee('Fournisseur 05')
        ->assertSee('7 fournisseurs')
        ->call('gotoPage', 2)
        ->assertSee('Fournisseur 05')
        ->assertDontSee('Fournisseur 00');
});

it('revient à la première page quand la recherche change', function () {
    Supplier::factory()->count(6)->create(['is_active' => true]);

    Livewire::test(Index::class)
        ->call('gotoPage', 2)
        ->set('search', 'Actif')
        ->assertSet('paginators.page', 1);
});
