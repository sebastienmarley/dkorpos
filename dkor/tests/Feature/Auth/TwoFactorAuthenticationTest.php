<?php

use App\Livewire\Settings\TwoFactor;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function currentOtp(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
}

// ── 2FA obligatoire ────────────────────────────────────────────────────────

it('renvoie vers l\'activation de la 2FA un compte sensible qui ne l\'a pas', function (string $role) {
    config(['access.two_factor.enforced' => true]);

    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('home'))
        ->assertRedirect(route('two-factor.edit'));
})->with(['admin', 'owner', 'accounting']);

it('exige la 2FA d\'un usager qui a accès à tous les magasins', function () {
    config(['access.two_factor.enforced' => true]);

    $manager = User::factory()->withRole('manager')->create();
    $manager->givePermissionTo('stores.all');

    $this->actingAs($manager)->get(route('home'))->assertRedirect(route('two-factor.edit'));
});

it('laisse passer un compte sensible qui a activé sa 2FA', function () {
    config(['access.two_factor.enforced' => true]);

    $this->actingAs(User::factory()->withRole('admin')->withTwoFactor()->create())
        ->get(route('home'))
        ->assertOk();
});

it('n\'exige pas la 2FA des autres rôles', function (string $role) {
    config(['access.two_factor.enforced' => true]);

    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('home'))
        ->assertOk();
})->with(['manager', 'salesman', 'delivery']);

it('affiche l\'activation imposée sans la navigation de l\'application', function () {
    config(['access.two_factor.enforced' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.edit'))
        ->assertOk()
        ->assertSee('Se déconnecter')
        ->assertDontSee(route('profile.edit'));
});

it('affiche le réglage facultatif avec la navigation de l\'application', function () {
    config(['access.two_factor.enforced' => true]);

    $this->actingAs(User::factory()->withRole('salesman')->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.edit'))
        ->assertOk()
        ->assertSee(route('profile.edit'));
});

it('demande le mot de passe avant d\'ouvrir la page d\'activation', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('two-factor.edit'))
        ->assertRedirect(route('password.confirm'));
});

// ── Activation par l'usager ────────────────────────────────────────────────

it('active la 2FA après la confirmation d\'un code valide', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(TwoFactor::class)->call('enable');

    expect($user->fresh()->two_factor_secret)->not->toBeNull()
        ->and($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $component->set('code', currentOtp($user))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('refuse un code invalide à la confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(TwoFactor::class)
        ->call('enable')
        ->set('code', '000000')
        ->call('confirm')
        ->assertHasErrors('code');

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('empêche un compte sensible de désactiver sa 2FA', function () {
    $this->actingAs(User::factory()->withRole('admin')->withTwoFactor()->create());

    Livewire::test(TwoFactor::class)->call('disable')->assertForbidden();
});

it('laisse un usager pour qui la 2FA est facultative la désactiver', function () {
    $user = User::factory()->withRole('salesman')->withTwoFactor()->create();
    $this->actingAs($user);

    Livewire::test(TwoFactor::class)->call('disable');

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

// ── Connexion ──────────────────────────────────────────────────────────────

it('connecte un usager qui a la 2FA après un code valide', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => currentOtp($user)]);

    $this->assertAuthenticatedAs($user);
});

it('refuse un mauvais code au défi de connexion', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});
