<?php

use App\Enums\InsurancePlan;
use App\Livewire\Users\Index;
use App\Livewire\Users\Show;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
        ->set('selectedPermissions', [...$user->getAllPermissions()->pluck('name')->all(), 'users.create'])
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
        ->set('selectedPermissions', $user->getAllPermissions()->pluck('name')->reject(fn ($n) => $n === 'users.create')->values()->all())
        ->call('saveAccess');

    expect($user->fresh()->hasDirectPermission('users.create'))->toBeFalse();
});

it('n\'accorde pas une permission que l\'acteur ne possède pas', function () {
    $user = employeComplet();

    $acteur = User::factory()->withRole('manager')->create();
    $acteur->givePermissionTo('users.assign_permissions');

    $this->actingAs($acteur);

    Livewire::test(Show::class, ['user' => $user])
        ->set('selectedPermissions', ['users.view', 'roles.manage'])
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

it('retire à un usager une permission fournie par son rôle', function () {
    $user = employeComplet();

    expect($user->can('customers.edit'))->toBeTrue();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('selectedPermissions', fn ($permissions) => in_array('customers.edit', $permissions, true))
        ->set('selectedPermissions', $user->getAllPermissions()->pluck('name')->reject(fn ($n) => $n === 'customers.edit')->values()->all())
        ->call('saveAccess')
        ->assertHasNoErrors()
        ->assertSet('selectedPermissions', fn ($permissions) => ! in_array('customers.edit', $permissions, true));

    $user = $user->fresh();

    expect($user->can('customers.edit'))->toBeFalse();
    expect($user->can('customers.create'))->toBeTrue();
    expect($user->deniedPermissionNames()->all())->toBe(['customers.edit']);
    expect($user->getAllPermissions()->pluck('name')->contains('customers.edit'))->toBeFalse();
});

it('rétablit une permission du rôle retirée précédemment', function () {
    $user = employeComplet();
    $user->deniedPermissions()->attach(Permission::findByName('customers.edit'));

    $this->actingAs(User::factory()->withRole('admin')->create());

    $user = $user->fresh();
    expect($user->can('customers.edit'))->toBeFalse();

    Livewire::test(Show::class, ['user' => $user])
        ->set('selectedPermissions', [...$user->getAllPermissions()->pluck('name')->all(), 'customers.edit'])
        ->call('saveAccess');

    $user = $user->fresh();

    expect($user->can('customers.edit'))->toBeTrue();
    expect($user->deniedPermissionNames())->toBeEmpty();
});

it('ne retire pas une permission que l\'acteur ne possède pas', function () {
    $role = Role::create(['name' => 'lead', 'label' => 'Chef', 'level' => 10, 'guard_name' => 'web']);
    $role->givePermissionTo(['customers.view', 'roles.manage']);

    $user = User::factory()->withRole('lead')->create();

    $acteur = User::factory()->withRole('manager')->create();
    $acteur->givePermissionTo('users.assign_permissions');

    $this->actingAs($acteur);

    Livewire::test(Show::class, ['user' => $user])
        ->set('selectedPermissions', [])
        ->call('saveAccess');

    $user = $user->fresh();

    expect($user->can('roles.manage'))->toBeTrue();
    expect($user->deniedPermissionNames()->contains('roles.manage'))->toBeFalse();
    expect($user->deniedPermissionNames()->contains('customers.view'))->toBeTrue();
});

it('applique le retrait aux pages protégées', function () {
    $user = employeComplet();
    $user->deniedPermissions()->attach(Permission::findByName('customers.view'));

    $this->actingAs($user->fresh())->get(route('customers.index'))->assertForbidden();
});

// ── RH ─────────────────────────────────────────────────────────────────────

it('sauvegarde les informations RH d\'un employé à taux horaire', function () {
    $admin = User::factory()->withRole('admin')->create();
    $user = employeComplet();

    $this->actingAs($admin);

    Livewire::test(Show::class, ['user' => $user])
        ->set('isFullTime', true)
        ->set('hasGroupInsurance', true)
        ->set('insurancePlan', 'single_parent')
        ->set('hourlyRate', '24.50')
        ->set('hasCommission', true)
        ->set('commissionRate', '2.75')
        ->call('saveHr')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $user->refresh();

    expect($user->is_full_time)->toBeTrue()
        ->and($user->has_group_insurance)->toBeTrue()
        ->and($user->insurance_plan)->toBe(InsurancePlan::SingleParent)
        ->and($user->is_salaried)->toBeFalse()
        ->and($user->hourly_rate)->toBe('24.50')
        ->and($user->weekly_salary)->toBeNull()
        ->and($user->has_commission)->toBeTrue()
        ->and($user->commission_rate)->toBe('2.75')
        ->and($user->last_modified_by)->toBe($admin->id);
});

it('remplace le taux horaire par le salaire hebdomadaire pour un salarié', function () {
    $user = employeComplet(['hourly_rate' => '20.00']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('hourlyRate', '20.00')
        ->set('isSalaried', true)
        ->set('hourlyRate', '20.00')
        ->set('weeklySalary', '1250.00')
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->is_salaried)->toBeTrue()
        ->and($user->weekly_salary)->toBe('1250.00')
        ->and($user->hourly_rate)->toBeNull();

    Livewire::test(Show::class, ['user' => $user])
        ->set('isSalaried', false)
        ->set('hourlyRate', '22.00')
        ->call('saveHr');

    $user->refresh();

    expect($user->hourly_rate)->toBe('22.00')->and($user->weekly_salary)->toBeNull();
});

it('exige le type d\'assurance quand les assurances collectives sont cochées', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasGroupInsurance', true)
        ->call('saveHr')
        ->assertHasErrors(['insurancePlan'])
        ->set('insurancePlan', 'inconnue')
        ->call('saveHr')
        ->assertHasErrors(['insurancePlan'])
        ->set('insurancePlan', 'family')
        ->call('saveHr')
        ->assertHasNoErrors();

    expect($user->fresh()->insurance_plan)->toBe(InsurancePlan::Family);
});

it('efface le type d\'assurance quand les assurances collectives sont décochées', function () {
    $user = employeComplet(['has_group_insurance' => true, 'insurance_plan' => InsurancePlan::Individual]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('insurancePlan', 'individual')
        ->set('hasGroupInsurance', false)
        ->assertSet('insurancePlan', null)
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->has_group_insurance)->toBeFalse()->and($user->insurance_plan)->toBeNull();
});

it('valide la commission et les montants', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasCommission', true)
        ->set('commissionRate', '100.5')
        ->set('hourlyRate', '-1')
        ->call('saveHr')
        ->assertHasErrors(['commissionRate', 'hourlyRate'])
        ->set('commissionRate', '2.555')
        ->set('hourlyRate', '20')
        ->call('saveHr')
        ->assertHasErrors(['commissionRate'])
        ->assertHasNoErrors(['hourlyRate']);
});

it('refuse un taux horaire qui n\'est pas un montant à 2 décimales au plus', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hourlyRate', $valeur)
        ->call('saveHr')
        ->assertHasErrors(['hourlyRate']);

    expect($user->fresh()->hourly_rate)->toBeNull();
})->with(['1e3', '2.5e1', '12.345', '0', '0.00', '0.0', 'abc', '12,50', '1_000', '-5', '+5', ' 5', '.5', '12.', '99999']);

it('accepte un taux horaire valide', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hourlyRate', $valeur)
        ->call('saveHr')
        ->assertHasNoErrors();
})->with(['24', '24.5', '24.50', '0.01', '9999.99']);

it('refuse un salaire hebdomadaire nul ou en notation scientifique', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('isSalaried', true)
        ->set('weeklySalary', $valeur)
        ->call('saveHr')
        ->assertHasErrors(['weeklySalary']);
})->with(['0', '1e3', '1250.123', 'abc']);

it('refuse une commission en notation scientifique ou à plus de 2 décimales', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasCommission', true)
        ->set('commissionRate', $valeur)
        ->call('saveHr')
        ->assertHasErrors(['commissionRate']);
})->with(['1e1', '2.555', '101', 'abc', '0']);

it('accepte une commission supérieure à 0 et jusqu\'à 100', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasCommission', true)
        ->set('commissionRate', $valeur)
        ->call('saveHr')
        ->assertHasNoErrors();
})->with(['0.01', '2.75', '100', '100.00']);

it('interdit de sauvegarder les informations RH sans permission', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    $component = Livewire::test(Show::class, ['user' => $user]);

    $this->actingAs(User::factory()->create());

    $component->call('saveHr')->assertForbidden();
});

// ── Permission dédiée aux informations RH ──────────────────────────────────

it('donne la permission RH à la comptabilité, à l\'administrateur et au propriétaire mais pas au directeur', function () {
    foreach (['accounting', 'admin', 'owner'] as $role) {
        $user = User::factory()->withRole($role)->create();
        expect($user->can('users.view_hr'))->toBeTrue()->and($user->can('users.edit_hr'))->toBeTrue();
    }

    $manager = User::factory()->withRole('manager')->create();
    expect($manager->can('users.view_hr'))->toBeFalse()->and($manager->can('users.edit_hr'))->toBeFalse();
});

it('ne montre pas l\'onglet RH ni ses données à un directeur', function () {
    $user = employeComplet(['hourly_rate' => '31.75', 'commission_rate' => '4.50']);

    $this->actingAs(User::factory()->withRole('manager')->create());

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertDontSee('Temps plein')
        ->assertDontSee('31.75')
        ->assertDontSee('4.50');

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('hourlyRate', null)
        ->assertSet('commissionRate', null)
        ->call('saveHr')
        ->assertForbidden();
});

it('permet à la comptabilité d\'ouvrir la fiche et de modifier uniquement les informations RH', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('accounting')->create());

    $this->get(route('users.show', $user))->assertOk()->assertSee('Temps plein')->assertDontSee('Réinitialiser');

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('activeTab', 'hr')
        ->set('hourlyRate', '27.00')
        ->call('saveHr')
        ->assertHasNoErrors()
        ->set('firstname', 'Autre')
        ->call('saveIdentification')
        ->assertForbidden();

    expect($user->fresh()->hourly_rate)->toBe('27.00');
});

it('interdit à la comptabilité de modifier ses propres informations RH', function () {
    $comptable = User::factory()->withRole('accounting')->create();

    $this->actingAs($comptable);

    Livewire::test(Show::class, ['user' => $comptable])->call('saveHr')->assertForbidden();
});

it('permet de voir sans modifier avec users.view_hr seul', function () {
    $lecteur = User::factory()->withRole('accounting')->create();
    $lecteur->givePermissionTo('users.view');
    $lecteur->revokePermissionTo('users.edit_hr');
    $lecteur->deniedPermissions()->attach(Permission::findByName('users.edit_hr'));

    $this->actingAs($lecteur->fresh());

    $this->get(route('users.show', employeComplet()))->assertOk();

    Livewire::test(Show::class, ['user' => employeComplet()])->call('saveHr')->assertForbidden();
});

it('affiche le lien vers la fiche à la comptabilité dans la liste', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('accounting')->create());

    Livewire::test(Index::class)->assertSee(route('users.show', $user));
});

// ── Commission et primes ───────────────────────────────────────────────────

it('exige le pourcentage quand la commission est cochée et efface la valeur sinon', function () {
    $user = employeComplet(['has_commission' => true, 'commission_rate' => '3.00']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('commissionRate', '3.00')
        ->set('commissionRate', '')
        ->call('saveHr')
        ->assertHasErrors(['commissionRate'])
        ->set('hasCommission', false)
        ->assertSet('commissionRate', null)
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->has_commission)->toBeFalse()->and($user->commission_rate)->toBeNull();
});

it('sauvegarde les primes par tranche', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasBonus', true)
        ->set('weeklySalesTarget', '10000')
        ->set('bonusAmount', '100')
        ->set('bonusStep', '2500')
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->has_bonus)->toBeTrue()
        ->and($user->weekly_sales_target)->toBe(10000)
        ->and($user->bonus_amount)->toBe(100)
        ->and($user->bonus_step)->toBe(2500);
});

it('exige les trois champs de prime quand les primes sont cochées', function () {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasBonus', true)
        ->call('saveHr')
        ->assertHasErrors(['weeklySalesTarget', 'bonusAmount', 'bonusStep']);
});

it('refuse les valeurs de prime non entières ou nulles', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('hasBonus', true)
        ->set('weeklySalesTarget', $valeur)
        ->set('bonusAmount', $valeur)
        ->set('bonusStep', $valeur)
        ->call('saveHr')
        ->assertHasErrors(['weeklySalesTarget', 'bonusAmount', 'bonusStep']);
})->with(['0', '10.5', '1e3', '-5', 'abc', '12345678']);

it('efface les champs de prime quand les primes sont décochées', function () {
    $user = employeComplet(['has_bonus' => true, 'weekly_sales_target' => 8000, 'bonus_amount' => 50, 'bonus_step' => 1000]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('weeklySalesTarget', '8000')
        ->set('hasBonus', false)
        ->assertSet('weeklySalesTarget', null)
        ->assertSet('bonusAmount', null)
        ->assertSet('bonusStep', null)
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->has_bonus)->toBeFalse()
        ->and($user->weekly_sales_target)->toBeNull()
        ->and($user->bonus_amount)->toBeNull()
        ->and($user->bonus_step)->toBeNull();
});

// ── Vacances ───────────────────────────────────────────────────────────────

it('affiche les jours de vacances cumulés et enregistre les heures par jour', function () {
    $this->travelTo(Carbon::parse('2026-10-31 12:00'));

    $user = employeComplet(['first_day' => '2020-01-15']);

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->assertSet('vacationDaysAccrued', '7.56')
        ->assertSet('vacationReferenceStart', '2026-05-01')
        ->assertSee('Jours de vacances cumulés')
        ->set('vacationHoursPerDay', '7.5')
        ->call('saveHr')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->vacation_hours_per_day)->toBe('7.50')
        ->and($user->vacation_hours_available)->toBe('56.70');
});

it('refuse des heures par jour invalides', function (string $valeur) {
    $user = employeComplet();

    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(Show::class, ['user' => $user])
        ->set('vacationHoursPerDay', $valeur)
        ->call('saveHr')
        ->assertHasErrors(['vacationHoursPerDay']);
})->with(['0', '25', '7.555', '1e1', 'abc']);

it('ne calcule pas les vacances pour qui ne voit pas l\'onglet RH', function () {
    $user = employeComplet(['first_day' => '2020-01-15']);

    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(Show::class, ['user' => $user])->assertSet('vacationDaysAccrued', null);
});
