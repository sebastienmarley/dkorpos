<?php

use App\Livewire\customerForm;
use App\Models\customer;
use App\Models\User;
use Livewire\Livewire;

// ── Création ───────────────────────────────────────────────────────────────

it('crée un client avec les champs obligatoires', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Richard')
        ->set('lastname', 'Valjean')
        ->call('save')
        ->assertHasNoErrors();

    expect(customer::where('firstname', 'Richard')->where('lastname', 'Valjean')->exists())->toBeTrue();
});

it('crée un client avec tous les champs remplis', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Marie')
        ->set('lastname', 'Cote')
        ->set('phone', '(514)555-1234')
        ->set('cellphone', '(438)555-5678')
        ->set('email', 'marie.cote@exemple.com')
        ->set('adress', '123 rue Principale')
        ->call('save')
        ->assertHasNoErrors();

    $client = customer::where('email', 'marie.cote@exemple.com')->first();

    expect($client)->not->toBeNull();
    expect($client->phone)->toBe('(514)555-1234');
    expect($client->cellphone)->toBe('(438)555-5678');
});

it('enregistre null pour un courriel vide afin d\'éviter la violation de contrainte unique', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Jean')
        ->set('lastname', 'Martin')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Pierre')
        ->set('lastname', 'Leblanc')
        ->call('save')
        ->assertHasNoErrors();

    expect(customer::whereNull('email')->count())->toBe(2);
});

it('ferme le modal et dispatche customer-saved après la création', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Alice')
        ->set('lastname', 'Roy')
        ->call('save')
        ->assertSet('showModal', false)
        ->assertDispatched('customer-saved');
});

// ── Modification ───────────────────────────────────────────────────────────

it('charge les données du client dans le modal d\'édition', function () {
    $client = customer::factory()->create([
        'firstname' => 'Luc',
        'lastname' => 'Gagnon',
        'phone' => '(514)555-0000',
        'email' => 'luc@exemple.com',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openEdit', $client->id)
        ->assertSet('firstname', 'Luc')
        ->assertSet('lastname', 'Gagnon')
        ->assertSet('phone', '(514)555-0000')
        ->assertSet('email', 'luc@exemple.com')
        ->assertSet('showModal', true);
});

it('met à jour un client existant', function () {
    $client = customer::factory()->create(['firstname' => 'Luc', 'lastname' => 'Gagnon']);

    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openEdit', $client->id)
        ->set('firstname', 'Lucas')
        ->set('phone', '(450)555-9999')
        ->call('save')
        ->assertHasNoErrors();

    expect($client->fresh()->firstname)->toBe('Lucas');
    expect($client->fresh()->phone)->toBe('(450)555-9999');
});

it('ferme le modal et dispatche customer-saved après la modification', function () {
    $client = customer::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openEdit', $client->id)
        ->call('save')
        ->assertSet('showModal', false)
        ->assertDispatched('customer-saved');
});

// ── Validation ─────────────────────────────────────────────────────────────

it('requiert le prénom et le nom', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['firstname', 'lastname']);
});

it('rejette un format de téléphone invalide', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Test')
        ->set('lastname', 'Client')
        ->set('phone', '5145551234')
        ->call('save')
        ->assertHasErrors(['phone']);
});

it('accepte un téléphone au format (xxx)xxx-xxxx', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Test')
        ->set('lastname', 'Client')
        ->set('phone', '(514)555-1234')
        ->call('save')
        ->assertHasNoErrors(['phone']);
});

it('accepte un téléphone vide', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Test')
        ->set('lastname', 'Client')
        ->set('phone', '')
        ->call('save')
        ->assertHasNoErrors(['phone']);
});

it('rejette un courriel dupliqué', function () {
    customer::factory()->create(['email' => 'doublon@exemple.com']);

    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openCreate')
        ->set('firstname', 'Test')
        ->set('lastname', 'Client')
        ->set('email', 'doublon@exemple.com')
        ->call('save')
        ->assertHasErrors(['email']);
});

it('autorise le même courriel lors de la modification du même client', function () {
    $client = customer::factory()->create(['email' => 'propre@exemple.com']);

    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->call('openEdit', $client->id)
        ->set('email', 'propre@exemple.com')
        ->call('save')
        ->assertHasNoErrors(['email']);
});

// ── État du modal ──────────────────────────────────────────────────────────

it('ouvre le modal en mode création avec les champs vides', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(customerForm::class)
        ->set('firstname', 'Données précédentes')
        ->call('openCreate')
        ->assertSet('showModal', true)
        ->assertSet('firstname', '')
        ->assertSet('customerId', null);
});
