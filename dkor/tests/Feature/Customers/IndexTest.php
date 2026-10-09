<?php

use App\Livewire\Customers\Index;
use App\Models\customer;
use App\Models\User;
use Livewire\Livewire;

// ── Accès ──────────────────────────────────────────────────────────────────

it('redirige les invités vers la page de connexion', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

it('autorise les utilisateurs authentifiés à accéder à la page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('customers.index'))->assertOk();
});

// ── Affichage initial ──────────────────────────────────────────────────────

it('n\'affiche aucun client sans critère de recherche', function () {
    customer::factory()->create(['firstname' => 'Alice', 'lastname' => 'Tremblay']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->assertDontSee('Alice Tremblay');
});

it('affiche le message d\'invite quand la recherche est vide', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->assertSee('Entrez un nom');
});

// ── Recherche ──────────────────────────────────────────────────────────────

it('affiche les clients correspondant à la recherche par nom', function () {
    $match = customer::factory()->create(['firstname' => 'Richard', 'lastname' => 'Valjean']);
    $other = customer::factory()->create(['firstname' => 'Marie', 'lastname' => 'Cote']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('search', 'Valjean')
        ->assertSee('Richard Valjean')
        ->assertDontSee('Marie Cote');
});

it('affiche les clients correspondant à la recherche par courriel', function () {
    $match = customer::factory()->create(['email' => 'richard@exemple.com']);
    $other = customer::factory()->withoutEmail()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('search', 'richard@exemple.com')
        ->assertSee('richard@exemple.com');
});

it('affiche les clients correspondant à la recherche par téléphone', function () {
    $match = customer::factory()->create(['phone' => '(514)555-1234', 'firstname' => 'Paul', 'lastname' => 'Dupont']);
    $other = customer::factory()->create(['phone' => '(438)555-9999', 'firstname' => 'Jean', 'lastname' => 'Martin']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('search', '514')
        ->assertSee('Paul Dupont')
        ->assertDontSee('Jean Martin');
});

it('affiche un message quand la recherche ne retourne aucun résultat', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('search', 'introuvable')
        ->assertSee('Aucun client trouvé');
});

// ── Recherche sans égard à la casse ni aux accents ────────────────────────

it('trouve un client sans égard à la casse ni aux accents', function (string $term) {
    customer::factory()->create(['firstname' => 'Élise', 'lastname' => 'Bérubé-Côté']);
    customer::factory()->create(['firstname' => 'Paul', 'lastname' => 'Gagnon']);

    expect(customer::query()->matching($term)->pluck('firstname')->all())->toBe(['Élise']);
})->with(['elise', 'ELISE', 'élise', 'EliSe', 'berube', 'BÉRUBÉ', 'cote', 'Côté', 'elise berube', 'berube elise', '  ÉLISE   cÔtÉ ']);

it('normalise le nom de recherche à l\'enregistrement', function () {
    $customer = customer::factory()->create(['firstname' => 'François', 'lastname' => 'Lefèbvre']);

    expect($customer->search_name)->toBe('francois lefebvre');

    $customer->update(['lastname' => 'À Côté']);

    expect($customer->fresh()->search_name)->toBe('francois a cote');
});

it('cherche dans la page clients sans égard aux accents', function () {
    customer::factory()->create(['firstname' => 'André', 'lastname' => 'Pâquet']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('search', 'andre paquet')
        ->assertSee('André');
});
