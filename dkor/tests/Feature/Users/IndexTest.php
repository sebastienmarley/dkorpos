<?php

use App\Livewire\Users\Index;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('autorise les utilisateurs ayant la permission users.view à accéder à la page', function () {
    $this->actingAs(User::factory()->create()->givePermissionTo('users.view'));

    $this->get(route('users.index'))->assertOk();
});

it('refuse l\'accès sans la permission users.view', function () {
    $role = Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $user = User::factory()->withRole('visiteur')->create();

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

// ── Affichage ──────────────────────────────────────────────────────────────

it('affiche uniquement les utilisateurs actifs', function () {
    $actif = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay', 'is_active' => true]);
    $inactif = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'Gagnon', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->assertSee($actif->fullName())
        ->assertDontSee($inactif->fullName());
});

// ── Filtre par rôle ────────────────────────────────────────────────────────

it('filtre les utilisateurs par rôle', function () {
    $admin = User::factory()->withRole('admin')->create(['is_active' => true]);
    $userRole = User::factory()->create(['is_active' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('sortByRole', 'admin')
        ->assertSee($admin->fullName())
        ->assertDontSee($userRole->fullName());
});

it('retire le filtre de rôle quand on rappelle sortByRole avec le même rôle', function () {
    $admin = User::factory()->withRole('admin')->create(['is_active' => true]);
    $userRole = User::factory()->create(['is_active' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('sortByRole', 'admin')
        ->call('sortByRole', 'admin')
        ->assertSee($admin->fullName())
        ->assertSee($userRole->fullName());
});

// ── Modal de création ──────────────────────────────────────────────────────

it('ouvre le modal de création', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true);
});

it('réinitialise les champs à l\'ouverture du modal', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Jean')
        ->call('openCreateModal')
        ->assertSet('firstname', '')
        ->assertSet('generatedEmail', '')
        ->assertSet('role', 'salesman');
});

// ── Génération du courriel ─────────────────────────────────────────────────

it('génère le courriel à partir du username lors de la saisie du nom', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->assertSet('generatedEmail', 'mariecote@dkor.ca');
});

it('met à jour le courriel généré après confirmNewEmployee', function () {
    User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'email' => 'lucroy@dkor.ca']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->call('confirmNewEmployee')
        ->assertSet('generatedEmail', 'lucroy1@dkor.ca');
});

// ── Création d'utilisateur ─────────────────────────────────────────────────

it('exige un magasin à la création d\'un utilisateur', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('cellphone', '(514)555-1234')
        ->call('save')
        ->assertHasErrors(['storeId' => 'required']);
});

it('crée un nouvel utilisateur avec le courriel généré', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('role', 'salesman')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
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

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->set('role', 'salesman')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertHasNoErrors();

    $nouveau = User::where('email', 'lucroy1@dkor.ca')->first();

    expect($nouveau)->not->toBeNull();
    expect($nouveau->username)->toBe('lucroy1');
});

// ── Validation ─────────────────────────────────────────────────────────────

it('requiert le prénom et le nom', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->call('save')
        ->assertHasErrors(['firstname', 'lastname', 'cellphone']);
});

// ── Recherche et statut ────────────────────────────────────────────────────

it('recherche un employé par nom', function () {
    $alice = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay', 'is_active' => true]);
    $bob = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'Gagnon', 'is_active' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('search', 'Tremb')
        ->assertSee($alice->fullName())
        ->assertDontSee($bob->fullName());
});

it('affiche les employés inactifs avec le filtre de statut', function () {
    $actif = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay', 'is_active' => true]);
    $inactif = User::factory()->create(['firstname' => 'Bob', 'lastname' => 'Gagnon', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('statusFilter', 'inactive')
        ->assertSee($inactif->fullName())
        ->assertDontSee($actif->fullName())
        ->set('statusFilter', 'all')
        ->assertSee($inactif->fullName())
        ->assertSee($actif->fullName());
});

// ── Mot de passe initial et réactivation ───────────────────────────────────

it('génère un mot de passe temporaire aléatoire à la création', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $component = Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertSet('showCredentialsModal', true)
        ->assertSet('createdEmail', 'mariecote@dkor.ca');

    $user = User::where('email', 'mariecote@dkor.ca')->first();
    $password = $component->get('createdPassword');

    expect($password)->not->toBe('password')->and(strlen($password))->toBeGreaterThanOrEqual(12);
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

it('ne réactive pas un employé inactif pendant la saisie', function () {
    $ancien = User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy');

    expect($ancien->fresh()->is_active)->toBeFalse();
});

it('demande nouvel employé ou retour quand un employé inactif porte le même nom', function () {
    User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->assertSet('inactiveMatchIds', fn ($ids) => count($ids) === 1)
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertSet('showCreateModal', true);

    expect(User::where('firstname', 'Luc')->where('lastname', 'Roy')->count())->toBe(1);
});

it('crée un nouvel employé avec un nouveau nom d\'utilisateur après confirmation', function () {
    User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'email' => 'lucroy@dkor.ca', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->call('confirmNewEmployee')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    expect(User::where('email', 'lucroy1@dkor.ca')->first())->is_active->toBeTrue();
    expect(User::where('username', 'lucroy')->first()->is_active)->toBeFalse();
});

it('redirige vers la fiche de l\'employé inactif quand il est le seul candidat', function () {
    $ancien = User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->call('confirmReturningEmployee')
        ->assertRedirect(route('users.show', $ancien));

    expect($ancien->fresh()->is_active)->toBeFalse();
});

it('filtre la liste sur les inactifs quand plusieurs employés correspondent', function () {
    $a = User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'is_active' => false]);
    $b = User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy1', 'email' => 'autre@dkor.ca', 'is_active' => false]);
    $autre = User::factory()->create(['firstname' => 'Luc', 'lastname' => 'Gagnon', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->call('confirmReturningEmployee')
        ->assertSet('statusFilter', 'inactive')
        ->assertSet('search', 'Luc Roy')
        ->assertSet('showCreateModal', false)
        ->assertSee('Luc Roy')
        ->assertDontSee('Luc Gagnon');
});

// ── Autorisations par rôle ─────────────────────────────────────────────────

it('interdit la création aux rôles qui ne gèrent pas les utilisateurs', function (string $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->assertForbidden();
})->with(['salesman', 'design', 'accounting', 'thirdkey']);

it('permet à Admin, Owner et Manager de créer un utilisateur', function (string $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'mariecote@dkor.ca')->exists())->toBeTrue();
})->with(['admin', 'owner', 'manager']);

it('interdit à un Manager de créer un Admin ou un Owner', function (string $cible) {
    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('role', $cible)
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save')
        ->assertHasErrors(['role']);

    expect(User::where('email', 'mariecote@dkor.ca')->exists())->toBeFalse();
})->with(['admin', 'owner']);

it('cache le bouton de création et les liens de fiche aux rôles non autorisés', function () {
    $cible = User::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay']);

    $this->actingAs(User::factory()->withRole('salesman')->create());

    Livewire::test(Index::class)
        ->assertDontSee('Ajouter un utilisateur')
        ->assertDontSee(route('users.show', $cible));
});

it('enregistre le créateur dans last_modified_by', function () {
    $admin = User::factory()->withRole('admin')->create();

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('openCreateModal')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('cellphone', '(514)555-1234')
        ->set('storeId', Store::factory()->create()->id)
        ->call('save');

    $user = User::where('email', 'mariecote@dkor.ca')->first();

    expect($user->last_modified_by)->toBe($admin->id)->and($user->last_modified)->not->toBeNull();
});

it('affiche le message d\'erreur en toast après une redirection', function () {
    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->withSession(['toast-error' => 'Vous ne pouvez pas modifier votre propre fiche.'])
        ->get(route('users.index'))
        ->assertSee('Vous ne pouvez pas modifier votre propre fiche.');
});

it('cache les Admin et Owner dans la liste d\'un Manager', function () {
    $admin = User::factory()->withRole('admin')->create(['firstname' => 'Ada', 'lastname' => 'Admin']);
    $owner = User::factory()->withRole('owner')->create(['firstname' => 'Otto', 'lastname' => 'Owner']);
    $vendeur = User::factory()->create(['firstname' => 'Vera', 'lastname' => 'Vendeuse']);

    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Index::class)
        ->assertSee($vendeur->fullName())
        ->assertDontSee($admin->fullName())
        ->assertDontSee($owner->fullName())
        ->set('statusFilter', 'all')
        ->assertDontSee($admin->fullName());
});

it('montre tous les rôles dans la liste d\'un Admin', function () {
    $owner = User::factory()->withRole('owner')->create(['firstname' => 'Otto', 'lastname' => 'Owner']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Index::class)->assertSee($owner->fullName());
});

it('ne révèle pas un Admin ou un Owner à un Manager lors de la création', function (string $cible) {
    User::factory()->withRole($cible)->create(['firstname' => 'Luc', 'lastname' => 'Roy', 'username' => 'lucroy', 'email' => 'lucroy@dkor.ca', 'is_active' => true]);
    User::factory()->withRole($cible)->create(['firstname' => 'Ana', 'lastname' => 'Roy', 'username' => 'anaroy', 'email' => 'anaroy@dkor.ca', 'is_active' => false]);

    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Index::class)
        ->set('firstname', 'Luc')
        ->set('lastname', 'Roy')
        ->assertSet('showDuplicatePrompt', false)
        ->assertSet('existingUser', null)
        ->assertSet('generatedEmail', 'lucroy1@dkor.ca')
        ->set('firstname', 'Ana')
        ->set('lastname', 'Roy')
        ->assertSet('inactiveMatchIds', []);
})->with(['admin', 'owner']);
