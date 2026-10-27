<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['access.session_lock.idle_minutes' => 5, 'access.session_lock.max_attempts' => 5]);
});

/**
 * Ouvre une page, puis laisse la session inactive au-delà du délai.
 */
function leaveSessionIdle(User $user): void
{
    test()->actingAs($user)->get(route('home'))->assertOk();
    test()->travel(6)->minutes();
}

// ── Verrouillage ──────────────────────────────────────────────────────────

it('verrouille la session après le délai d\'inactivité', function () {
    leaveSessionIdle(User::factory()->withPin()->create());

    $this->get(route('home'))->assertRedirect(route('session-lock.show'));
});

it('garde la session ouverte tant que l\'usager est actif', function () {
    $this->actingAs(User::factory()->withPin()->create())->get(route('home'));
    $this->travel(4)->minutes();
    $this->postJson(route('session-lock.heartbeat'))->assertJson(['locked' => false]);
    $this->travel(4)->minutes();

    $this->get(route('home'))->assertOk();
});

it('n\'ouvre pas une session déjà verrouillée par un battement de cœur', function () {
    leaveSessionIdle(User::factory()->withPin()->create());

    $this->postJson(route('session-lock.heartbeat'))->assertJson(['locked' => true]);
    $this->get(route('home'))->assertRedirect(route('session-lock.show'));
});

it('refuse les requêtes de la page d\'une session verrouillée', function () {
    leaveSessionIdle(User::factory()->withPin()->create());

    $this->getJson(route('home'))->assertStatus(423);
});

it('verrouille tout de suite à la demande de l\'usager', function () {
    $this->actingAs(User::factory()->withPin()->create())->get(route('home'));

    $this->postJson(route('session-lock.lock'))->assertOk();

    $this->get(route('customers.index'))->assertRedirect(route('session-lock.show'));
});

it('ne verrouille pas un usager qui n\'a pas de NIP', function () {
    leaveSessionIdle(User::factory()->create());

    $this->get(route('home'))->assertOk();
});

it('verrouille un usager reconnecté par « Se souvenir de moi »', function () {
    $user = User::factory()->withPin()->create();
    $recaller = $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword();

    $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
        ->get(route('home'))
        ->assertRedirect(route('session-lock.show'));

    $this->assertAuthenticatedAs($user);
});

// ── Déverrouillage ────────────────────────────────────────────────────────

it('affiche l\'écran de verrouillage au nom de l\'usager', function () {
    $user = User::factory()->withPin()->create();
    leaveSessionIdle($user);

    $this->get(route('session-lock.show'))
        ->assertOk()
        ->assertSee($user->fullName())
        ->assertSee('Changer d\'utilisateur');
});

it('déverrouille avec le bon NIP et revient à la page demandée', function () {
    leaveSessionIdle(User::factory()->withPin('1234')->create());

    $this->get(route('customers.index'));

    $this->post(route('session-lock.unlock'), ['pin' => '1234'])->assertRedirect(route('customers.index'));
    $this->get(route('home'))->assertOk();
});

it('déverrouille l\'écran superposé à la page sans la recharger', function () {
    leaveSessionIdle(User::factory()->withPin('1234')->create());

    $this->postJson(route('session-lock.unlock'), ['pin' => '1234'])->assertJson(['locked' => false]);
    $this->getJson(route('home'))->assertOk();
});

it('refuse un mauvais NIP en indiquant les essais restants', function () {
    leaveSessionIdle(User::factory()->withPin('1234')->create());

    $this->postJson(route('session-lock.unlock'), ['pin' => '0000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => 'Il reste 4 essais.']);

    $this->get(route('home'))->assertRedirect(route('session-lock.show'));
});

it('déconnecte l\'usager après 5 mauvais NIP', function () {
    leaveSessionIdle(User::factory()->withPin('1234')->create());

    foreach (range(1, 4) as $attempt) {
        $this->postJson(route('session-lock.unlock'), ['pin' => '0000'])->assertUnprocessable();
    }

    $this->postJson(route('session-lock.unlock'), ['pin' => '0000'])
        ->assertUnauthorized()
        ->assertJson(['redirect' => route('login')]);

    $this->assertGuest();
});

it('permet de changer d\'utilisateur pendant le verrouillage', function () {
    leaveSessionIdle(User::factory()->withPin()->create());

    $this->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});

it('renvoie à l\'accueil l\'écran de verrouillage d\'une session ouverte', function () {
    $this->actingAs(User::factory()->withPin()->create())
        ->get(route('session-lock.show'))
        ->assertRedirect(route('home'));
});
