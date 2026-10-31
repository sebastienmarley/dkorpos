<?php

use App\Livewire\Settings\Pin;
use App\Models\User;
use App\Support\SessionLock;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

// ── NIP obligatoire ────────────────────────────────────────────────────────

it('renvoie vers la création du NIP un usager qui n\'en a pas', function () {
    config(['access.pin.enforced' => true]);

    $this->actingAs(User::factory()->withRole('salesman')->create())
        ->get(route('home'))
        ->assertRedirect(route('pin.edit'));
});

it('fait activer la 2FA obligatoire avant de créer le NIP', function () {
    config(['access.two_factor.enforced' => true, 'access.pin.enforced' => true]);

    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->get(route('home'))->assertRedirect(route('two-factor.edit'));
    $this->get(route('pin.edit'))->assertRedirect(route('two-factor.edit'));
});

it('demande le NIP une fois la 2FA obligatoire activée', function () {
    config(['access.two_factor.enforced' => true, 'access.pin.enforced' => true]);

    $this->actingAs(User::factory()->withRole('admin')->withTwoFactor()->create())
        ->get(route('home'))
        ->assertRedirect(route('pin.edit'));
});

it('laisse passer un usager qui a son NIP', function () {
    config(['access.pin.enforced' => true]);

    $this->actingAs(User::factory()->withRole('salesman')->withPin()->create())
        ->get(route('home'))
        ->assertOk();
});

it('affiche la création imposée sans la navigation de l\'application', function () {
    config(['access.pin.enforced' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('pin.edit'))
        ->assertOk()
        ->assertSee('Se déconnecter')
        ->assertDontSee(route('profile.edit'));
});

// ── Création et changement ────────────────────────────────────────────────

it('crée le NIP et envoie l\'usager à l\'accueil', function () {
    config(['access.pin.enforced' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Pin::class)
        ->set('pin', '482913')
        ->set('pinConfirmation', '482913')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Hash::check('482913', $user->fresh()->pin))->toBeTrue();
});

it('démarre le verrouillage à la création du NIP, même après une longue attente', function () {
    config(['access.pin.enforced' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);
    app(SessionLock::class)->touch();
    $this->travel(1)->hour();

    Livewire::test(Pin::class)
        ->set('pin', '482913')
        ->set('pinConfirmation', '482913')
        ->call('save');

    $this->get(route('home'))->assertOk();
});

it('refuse un NIP invalide', function (string $pin, string $confirmation, string $field) {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Pin::class)
        ->set('pin', $pin)
        ->set('pinConfirmation', $confirmation)
        ->call('save')
        ->assertHasErrors([$field]);

    expect($user->fresh()->pin)->toBeNull();
})->with([
    'trop court' => ['123', '123', 'pin'],
    'trop long' => ['1234567', '1234567', 'pin'],
    'pas des chiffres' => ['12ab', '12ab', 'pin'],
    'confirmation différente' => ['1234', '4321', 'pinConfirmation'],
]);

it('change le NIP avec le mot de passe actuel', function () {
    $user = User::factory()->withPin('1234')->create();
    $this->actingAs($user);

    Livewire::test(Pin::class)
        ->set('pin', '5678')
        ->set('pinConfirmation', '5678')
        ->set('currentPassword', 'password')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('5678', $user->fresh()->pin))->toBeTrue();
});

it('exige le mot de passe actuel pour changer le NIP', function () {
    $user = User::factory()->withPin('1234')->create();
    $this->actingAs($user);

    Livewire::test(Pin::class)
        ->set('pin', '5678')
        ->set('pinConfirmation', '5678')
        ->set('currentPassword', 'wrong-password')
        ->call('save')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('1234', $user->fresh()->pin))->toBeTrue();
});

it('ne divulgue pas le NIP dans la sérialisation', function () {
    expect(User::factory()->withPin()->create()->toArray())->not->toHaveKey('pin');
});
