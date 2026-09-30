<?php

use App\Enums\RoleType;
use App\Livewire\Users\Show;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function employeComplet(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'firstname' => 'Alice',
        'lastname' => 'Tremblay',
        'role' => 'salesman',
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

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

    $this->get(route('users.show', $user))->assertOk()->assertSee('Alice Tremblay');

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('firstname', 'Alice')
        ->assertSet('firstDay', '2026-01-05')
        ->assertSet('personalEmail', 'alice@exemple.com');
});

it('sauvegarde l\'identification', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('firstname', 'Alicia')
        ->set('role', 'manager')
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    expect($user->fresh())->firstname->toBe('Alicia')->role->value->toBe('manager');
});

it('exige les champs obligatoires pour sauvegarder l\'identification', function () {
    $user = User::factory()->create(['first_day' => null, 'personal_email' => null, 'cellphone' => null]);

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

    Livewire::test(Show::class, ['user' => $user])
        ->call('saveIdentification')
        ->assertHasErrors(['firstDay', 'personalEmail', 'cellphone']);
});

it('refuse un dernier jour avant le premier jour', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('lastDay', '2025-12-31')
        ->call('saveIdentification')
        ->assertHasErrors(['lastDay']);
});

it('désactive un employé sans exiger l\'identification complète', function () {
    $user = User::factory()->create(['first_day' => null, 'personal_email' => null, 'cellphone' => null, 'is_active' => true]);

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('isActive', false)
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($user->fresh()->is_active)->toBeFalse();
});

it('réinitialise le mot de passe et affiche le nouveau en clair', function () {
    $user = employeComplet();
    $ancienHash = $user->password;

    $this->actingAs(User::factory()->withRole(RoleType::Admin)->create());

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
    $this->actingAs(User::factory()->withRole(RoleType::Salesman)->create());

    $this->get(route('users.show', employeComplet()))->assertForbidden();
});

it('cache la fiche d\'un Admin ou d\'un Owner à un Manager', function (RoleType $cible) {
    $this->actingAs(User::factory()->withRole(RoleType::Manager)->create());

    $this->get(route('users.show', employeComplet(['role' => $cible])))->assertNotFound();
})->with([RoleType::Admin, RoleType::Owner]);

it('permet à un Manager de modifier un autre rôle mais sans attribuer Admin ou Owner', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole(RoleType::Manager)->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('role', 'design')
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->set('role', 'admin')
        ->call('saveIdentification')
        ->assertHasErrors(['role']);

    expect($user->fresh()->role)->toBe(RoleType::Design);
});

it('permet à un Owner de modifier un Admin', function () {
    $this->actingAs(User::factory()->withRole(RoleType::Owner)->create());

    $this->get(route('users.show', employeComplet(['role' => RoleType::Admin])))->assertOk();
});

it('redirige un Manager qui ouvre sa propre fiche avec un message', function () {
    $manager = User::factory()->withRole(RoleType::Manager)->create();

    $this->actingAs($manager);

    $this->get(route('users.show', $manager))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('toast-error', 'Vous ne pouvez pas modifier votre propre fiche.');
});

it('enregistre qui a modifié la fiche et quand', function () {
    $admin = User::factory()->withRole(RoleType::Admin)->create();
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
    $admin = User::factory()->withRole(RoleType::Admin)->create();
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
