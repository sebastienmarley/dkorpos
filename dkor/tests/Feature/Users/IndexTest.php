<?php

use App\Livewire\Users\Index;
use App\Models\User;
use Livewire\Livewire;

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('autorise les utilisateurs authentifiés à accéder à la page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('users.index'))->assertOk();
});

// ── Affichage ──────────────────────────────────────────────────────────────

it('affiche uniquement les utilisateurs actifs', function () {
    $actif = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay', 'is_active' => true]);
    $inactif = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'Gagnon', 'is_active' => false]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->assertSee($actif->fullName())
        ->assertDontSee($inactif->fullName());
});

// ── Filtre par rôle ────────────────────────────────────────────────────────

it('filtre les utilisateurs par rôle', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $userRole = User::factory()->create(['role' => 'user', 'is_active' => true]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('sortByRole', 'admin')
        ->assertSee($admin->fullName())
        ->assertDontSee($userRole->fullName());
});

it('retire le filtre de rôle quand on rappelle sortByRole avec le même rôle', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $userRole = User::factory()->create(['role' => 'user', 'is_active' => true]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('sortByRole', 'admin')
        ->call('sortByRole', 'admin')
        ->assertSee($admin->fullName())
        ->assertSee($userRole->fullName());
});

// ── Modal de création ──────────────────────────────────────────────────────

it('ouvre le modal de création', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true);
});

it('réinitialise les champs à l\'ouverture du modal', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Jean')
        ->call('openCreateModal')
        ->assertSet('firstname', '')
        ->assertSet('generatedEmail', '')
        ->assertSet('role', 'user');
});

// ── Génération du courriel ─────────────────────────────────────────────────

it('génère le courriel à partir du username lors de la saisie du nom', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->assertSet('generatedEmail', 'mariecote@dkor.ca');
});

it('met à jour le courriel généré après confirmNewEmployee', function () {
    User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'email' => 'lucroy@dkor.ca']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->call('confirmNewEmployee')
        ->assertSet('generatedEmail', 'lucroy1@dkor.ca');
});

// ── Création d'utilisateur ─────────────────────────────────────────────────

it('crée un nouvel utilisateur avec le courriel généré', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('role', 'user')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    $user = User::where('email', 'mariecote@dkor.ca')->first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('mariecote@dkor.ca');
    expect($user->is_active)->toBeTrue();
});

it('génère un username et courriel uniques en cas de doublon de nom', function () {
    User::factory()->create([
        'firstname' => 'Luc',
        'lastname' => 'Roy',
        'username' => 'lucroy',
        'email' => 'lucroy@dkor.ca',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->set('role', 'user')
        ->call('save')
        ->assertHasNoErrors();

    $nouveau = User::where('email', 'lucroy1@dkor.ca')->first();

    expect($nouveau)->not->toBeNull();
    expect($nouveau->username)->toBe('lucroy1');
});

// ── Validation ─────────────────────────────────────────────────────────────

it('requiert le prénom et le nom', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->call('save')
        ->assertHasErrors(['firstname', 'lastname']);
});
