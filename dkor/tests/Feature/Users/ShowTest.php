<?php

use App\Livewire\Users\Show;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function employeComplet(array $attributes = []): User
{
    $role = Arr::pull($attributes, 'role', 'salesman');

    return User::factory()->withRole($role)->create(array_merge([
        'firstname' => 'Alice',
        'lastname' => 'Tremblay',
        'first_day' => '2026-01-05',
        'personal_email' => 'alice@exemple.com',
        'cellphone' => '(514)555-1234',
    ], $attributes));
}

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('users.show', employeComplet()))->assertRedirect(route('login'));
});

it('affiche la fiche et préremplit les champs', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->get(route('users.show', $user))->assertOk()->assertSee('Alice Tremblay');

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('firstname', 'Alice')
        ->assertSet('firstDay', '2026-01-05')
        ->assertSet('personalEmail', 'alice@exemple.com');
});

it('sauvegarde l\'identification', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('firstname', 'Alicia')
        ->set('role', 'manager')
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    expect($user->fresh())->firstname->toBe('Alicia')->role->name->toBe('manager');
});

it('exige les champs obligatoires pour sauvegarder l\'identification', function () {
    $user = User::factory()->create(['first_day' => null, 'personal_email' => null, 'cellphone' => null]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->call('saveIdentification')
        ->assertHasErrors(['firstDay', 'cellphone'])
        ->assertHasNoErrors(['personalEmail']);
});

it('refuse un dernier jour avant le premier jour', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('lastDay', '2025-12-31')
        ->call('saveIdentification')
        ->assertHasErrors(['lastDay']);
});

it('désactive un employé sans exiger l\'identification complète', function () {
    $user = User::factory()->create(['first_day' => null, 'personal_email' => null, 'cellphone' => null, 'is_active' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('isActive', false)
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($user->fresh()->is_active)->toBeFalse();
});

it('réinitialise le mot de passe et affiche le nouveau en clair', function () {
    $user = employeComplet();
    $ancienHash = $user->password;

    $this->actingAs(User::factory()->withRole('admin')->create());

    $component = Livewire::test(Show::class, ['user' => $user])
        ->assertSet('generatedPassword', null)
        ->call('resetPassword')
        ->assertSet('generatedPassword', fn ($value) => filled($value) && strlen($value) >= 12);

    $user->refresh();

    expect($user->password)->not->toBe($ancienHash);
    expect(Hash::check($component->get('generatedPassword'), $user->password))->toBeTrue();
});

// ── Autorisations par rôle ─────────────────────────────────────────────────

it('interdit l\'accès à la fiche aux rôles qui ne gèrent pas les utilisateurs', function () {
    $this->actingAs(User::factory()->withRole('salesman')->create());

    $this->get(route('users.show', employeComplet()))->assertForbidden();
});

it('cache la fiche d\'un Admin ou d\'un Owner à un Manager', function (string $cible) {
    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->get(route('users.show', employeComplet(['role' => $cible])))->assertNotFound();
})->with(['admin', 'owner']);

it('permet à un Manager de modifier un autre rôle mais sans attribuer Admin ou Owner', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('role', 'design')
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->set('role', 'admin')
        ->call('saveIdentification')
        ->assertHasErrors(['role']);

    expect($user->fresh()->role->name)->toBe('design');
});

it('permet à un Owner de modifier un Admin', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());

    $this->get(route('users.show', employeComplet(['role' => 'admin'])))->assertOk();
});

it('redirige un Manager qui ouvre sa propre fiche avec un message', function () {
    $manager = User::factory()->withRole('manager')->create();

    $this->actingAs($manager);

    $this->get(route('users.show', $manager))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('toast-error', 'Vous ne pouvez pas modifier votre propre fiche.');
});

it('enregistre qui a modifié la fiche et quand', function () {
    $admin = User::factory()->withRole('admin')->create();
    $user = employeComplet();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(14, 35));

    Livewire::test(Show::class, ['user' => $user])
        ->call('saveIdentification')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->last_modified_by)->toBe($admin->id);
    expect($user->last_modified->format('Y-m-d H:i'))->toBe(now()->format('Y-m-d H:i'));
});

it('enregistre l\'auteur lors du changement de statut et de la réinitialisation du mot de passe', function () {
    $admin = User::factory()->withRole('admin')->create();
    $user = employeComplet();

    $this->actingAs($admin);

    Livewire::test(Show::class, ['user' => $user])->set('isActive', false)->call('saveAccount');
    expect($user->fresh()->last_modified_by)->toBe($admin->id);

    $user->forceFill(['last_modified_by' => null, 'last_modified' => null])->save();

    Livewire::test(Show::class, ['user' => $user])->call('resetPassword');
    expect($user->fresh()->last_modified_by)->toBe($admin->id);
});

it('ne divulgue pas les champs de modification dans la sérialisation', function () {
    $user = employeComplet()->markModifiedBy(User::factory()->create());

    expect($user->toArray())->not->toHaveKeys(['last_modified', 'last_modified_by']);
});

// ── Rôles en base, positions et permissions supplémentaires ────────────────

it('assigne la position choisie à l\'employé', function () {
    $user = employeComplet();
    $position = Position::factory()->create();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('positionId', $position->id)
        ->call('saveIdentification')
        ->assertHasNoErrors();

    expect($user->fresh()->position_id)->toBe($position->id);
});

it('permet à un rôle personnalisé de gérer selon son niveau', function () {
    $role = Role::create(['name' => 'supervisor', 'label' => 'Superviseur', 'level' => 30, 'guard_name' => 'web']);
    $role->givePermissionTo(['users.view', 'users.edit']);

    $superviseur = User::factory()->create();
    $superviseur->assignRole($role);

    $this->actingAs($superviseur);

    $this->get(route('users.show', employeComplet()))->assertOk();
    $this->get(route('users.show', employeComplet(['role' => 'manager'])))->assertNotFound();
});

it('permet l\'édition de sa propre fiche avec users.edit_self', function () {
    $employe = employeComplet();
    $employe->givePermissionTo('users.edit_self');

    $this->actingAs($employe);

    $this->get(route('users.show', $employe))->assertOk();
});

it('attribue des permissions supplémentaires que l\'acteur possède', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('extraPermissions', ['users.create'])
        ->call('saveAccess')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->hasDirectPermission('users.create'))->toBeTrue();
    expect($user->can('users.create'))->toBeTrue();
});

it('retire une permission supplémentaire', function () {
    $user = employeComplet();
    $user->givePermissionTo('users.create');

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('extraPermissions', [])
        ->call('saveAccess');

    expect($user->fresh()->hasDirectPermission('users.create'))->toBeFalse();
});

it('n\'accorde pas une permission que l\'acteur ne possède pas', function () {
    $user = employeComplet();

    $acteur = User::factory()->withRole('manager')->create();
    $acteur->givePermissionTo('users.assign_permissions');

    $this->actingAs($acteur);

    Livewire::test(Show::class, ['user' => $user])
        ->set('extraPermissions', ['users.view', 'roles.manage'])
        ->call('saveAccess');

    expect($user->fresh()->hasDirectPermission('roles.manage'))->toBeFalse();
});

it('interdit l\'onglet Accès sans users.assign_permissions', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->call('saveAccess')
        ->assertForbidden();
});

it('interdit de s\'attribuer soi-même des permissions', function () {
    $admin = User::factory()->withRole('admin')->create();

    $this->actingAs($admin);

    Livewire::test(Show::class, ['user' => $admin])
        ->call('saveAccess')
        ->assertForbidden();
});

// ── Adresse ────────────────────────────────────────────────────────────────

it('préremplit et sauvegarde l\'adresse de l\'employé', function () {
    $admin = User::factory()->withRole('admin')->create();
    $user = employeComplet(['address_city' => 'Laval', 'address_country' => 'CA']);

    $this->actingAs($admin);

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('address.city', 'Laval')
        ->set('address', [
            'civic' => '123', 'apartment' => '4', 'street' => 'rue Principale',
            'city' => 'Montréal', 'province' => 'QC', 'country' => 'CA', 'postal_code' => 'H2X1Y4',
        ])
        ->call('saveAddress')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    expect($user->address_street)->toBe('rue Principale')
        ->and($user->address_city)->toBe('Montréal')
        ->and($user->address_province)->toBe('QC')
        ->and($user->address_postal_code)->toBe('H2X1Y4')
        ->and($user->last_modified_by)->toBe($admin->id);
});

it('valide l\'adresse et vide la province au changement de pays', function () {
    $user = employeComplet(['address_province' => 'QC']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('address.country', 'FR')
        ->call('saveAddress')
        ->assertHasErrors(['address.country'])
        ->set('address.country', 'US')
        ->assertSet('address.province', '');
});

it('interdit de sauvegarder l\'adresse sans la permission de modifier', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    $component = Livewire::test(Show::class, ['user' => $user]);

    $this->actingAs(User::factory()->create());

    $component->call('saveAddress')->assertForbidden();
});
