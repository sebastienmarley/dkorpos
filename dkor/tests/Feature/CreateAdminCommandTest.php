<?php

use App\Enums\StoreType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('crée le premier administrateur et le premier magasin d\'une installation neuve', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Prénom', 'Marie')
        ->expectsQuestion('Nom', 'Gagnon')
        ->expectsQuestion('Aucun magasin n\'existe. Nom du premier magasin', 'Bureau chef')
        ->expectsQuestion('Mot de passe', 'secret-1234')
        ->expectsQuestion('Confirmez le mot de passe', 'secret-1234')
        ->expectsOutputToContain('Administrateur créé')
        ->assertSuccessful();

    $admin = User::role('admin')->sole();
    $store = Store::sole();

    expect($admin->firstname)->toBe('Marie')
        ->and($admin->store_id)->toBe($store->id)
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('secret-1234', $admin->password))->toBeTrue()
        ->and($store->name)->toBe('Bureau chef')
        ->and($store->type)->toBe(StoreType::Physical);
});

it('rattache l\'administrateur à un magasin existant', function () {
    Store::factory()->create(['name' => 'Laval']);
    $montreal = Store::factory()->create(['name' => 'Montréal']);

    $this->artisan('app:create-admin')
        ->expectsQuestion('Prénom', 'Marie')
        ->expectsQuestion('Nom', 'Gagnon')
        ->expectsChoice('Magasin d\'appartenance', (string) $montreal->id, Store::pluck('name', 'id')->all())
        ->expectsQuestion('Mot de passe', 'secret-1234')
        ->expectsQuestion('Confirmez le mot de passe', 'secret-1234')
        ->assertSuccessful();

    expect(User::role('admin')->sole()->store_id)->toBe($montreal->id)
        ->and(Store::count())->toBe(2);
});

it('refuse de créer un second administrateur', function () {
    User::factory()->withRole('admin')->create();

    $this->artisan('app:create-admin')->assertFailed();

    expect(User::role('admin')->count())->toBe(1);
});

it('ne crée rien si la confirmation du mot de passe diffère', function () {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Prénom', 'Marie')
        ->expectsQuestion('Nom', 'Gagnon')
        ->expectsQuestion('Aucun magasin n\'existe. Nom du premier magasin', 'Bureau chef')
        ->expectsQuestion('Mot de passe', 'secret-1234')
        ->expectsQuestion('Confirmez le mot de passe', 'autre-1234')
        ->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Store::count())->toBe(0);
});
