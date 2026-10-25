<?php

use App\Enums\StoreType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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

it('refuse de rouler dès qu\'un usager existe, administrateur ou non, actif ou non', function (string $role, bool $isActive) {
    User::factory()->withRole($role)->create(['is_active' => $isActive]);

    $this->artisan('app:create-admin', ['--firstname' => 'Marie', '--lastname' => 'Gagnon', '--store' => 'Bureau chef', '--no-interaction' => true])
        ->assertFailed();

    expect(User::count())->toBe(1)
        ->and(User::role('admin')->count())->toBe($role === 'admin' ? 1 : 0);
})->with([
    'un administrateur' => ['admin', true],
    'un administrateur désactivé' => ['admin', false],
    'un propriétaire seulement' => ['owner', true],
]);

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

it('crée l\'administrateur sans terminal interactif, avec un mot de passe généré affiché une seule fois', function () {
    Artisan::call('app:create-admin', [
        '--firstname' => 'Marie',
        '--lastname' => 'Gagnon',
        '--store' => 'Bureau chef',
        '--no-interaction' => true,
    ]);

    preg_match('/Mot de passe \(affiché une seule fois\) : (\S+)/', Artisan::output(), $matches);
    $admin = User::role('admin')->sole();

    expect($matches)->toHaveKey(1)
        ->and(Hash::check($matches[1], $admin->password))->toBeTrue()
        ->and($admin->store->name)->toBe('Bureau chef');
});

it('exige les options sans terminal interactif', function () {
    $this->artisan('app:create-admin', ['--firstname' => 'Marie', '--lastname' => 'Gagnon', '--no-interaction' => true])
        ->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Store::count())->toBe(0);
});

it('refuse un magasin inconnu quand des magasins existent', function () {
    Store::factory()->create(['name' => 'Laval']);

    $this->artisan('app:create-admin', ['--firstname' => 'Marie', '--lastname' => 'Gagnon', '--store' => 'Montréal', '--no-interaction' => true])
        ->assertFailed();

    expect(User::role('admin')->count())->toBe(0)
        ->and(Store::count())->toBe(1);
});
