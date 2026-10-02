<?php

use App\Livewire\Admin\Positions;
use App\Models\Position;
use App\Models\User;
use Livewire\Livewire;

it('refuse l\'accès sans la permission positions.manage', function () {
    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->get(route('admin.positions'))->assertForbidden();
});

it('affiche la page à un administrateur', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->get(route('admin.positions'))->assertOk();
});

it('accorde l\'accès à un usager avec la permission directe', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('positions.manage');

    $this->actingAs($user)->get(route('admin.positions'))->assertOk();
});

it('crée et modifie une position', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Positions::class)
        ->call('openCreate')
        ->set('name', 'Conseiller senior')
        ->call('save')
        ->assertHasNoErrors();

    $position = Position::where('name', 'Conseiller senior')->firstOrFail();

    Livewire::test(Positions::class)
        ->call('openEdit', $position)
        ->set('name', 'Conseiller principal')
        ->call('save')
        ->assertHasNoErrors();

    expect($position->fresh()->name)->toBe('Conseiller principal');
});

it('exige un nom unique', function () {
    Position::factory()->create(['name' => 'Livreur']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Positions::class)
        ->call('openCreate')
        ->set('name', 'Livreur')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('ne supprime pas une position attribuée', function () {
    $position = Position::factory()->create();
    User::factory()->create(['position_id' => $position->id]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Positions::class)->call('delete', $position);

    expect(Position::find($position->id))->not->toBeNull();
});

it('supprime une position sans employé', function () {
    $position = Position::factory()->create();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Positions::class)->call('delete', $position);

    expect(Position::find($position->id))->toBeNull();
});

it('interdit les actions sans permission', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Positions::class)->call('openCreate')->assertForbidden();
});
