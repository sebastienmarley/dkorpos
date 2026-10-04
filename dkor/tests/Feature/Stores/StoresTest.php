<?php

use App\Enums\StoreType;
use App\Livewire\StoreForm;
use App\Livewire\Stores\Index;
use App\Livewire\Stores\Show;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

dataset('privilegedRoles', ['owner', 'admin']);
dataset('otherRoles', ['manager', 'salesman', 'accounting']);

it('autorise propriétaire et administrateur à voir les magasins', function (string $role) {
    $store = Store::factory()->create();

    $this->actingAs(User::factory()->withRole($role)->create());

    $this->get(route('stores.index'))->assertOk();
    $this->get(route('stores.show', $store))->assertOk();
})->with('privilegedRoles');

it('refuse les autres rôles', function (string $role) {
    $store = Store::factory()->create();

    $this->actingAs(User::factory()->withRole($role)->create());

    $this->get(route('stores.index'))->assertForbidden();
    $this->get(route('stores.show', $store))->assertForbidden();
})->with('otherRoles');

it('crée un magasin avec son nom et son type', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());

    Livewire::test(StoreForm::class)
        ->call('openCreate')
        ->set('name', 'Boutique en ligne')
        ->set('type', 'virtual')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('store-saved');

    $store = Store::firstWhere('name', 'Boutique en ligne');
    expect($store->type)->toBe(StoreType::Virtual)
        ->and($store->opening_hours['monday']['open'])->toBeTrue();
});

it('exige un nom et un type valide à la création', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());

    Livewire::test(StoreForm::class)
        ->set('name', '')
        ->set('type', 'autre')
        ->call('save')
        ->assertHasErrors(['name', 'type']);
});

it('refuse la création sans permission', function () {
    $this->actingAs(User::factory()->withRole('manager')->create());

    Livewire::test(StoreForm::class)->call('openCreate')->assertForbidden();
});

it('filtre la liste par recherche et statut', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    Store::factory()->create(['name' => 'Montréal']);
    Store::factory()->create(['name' => 'Québec', 'is_active' => false]);

    Livewire::test(Index::class)->assertSee('Montréal')->assertDontSee('Québec')
        ->set('showInactive', true)->assertSee('Québec')
        ->set('search', 'Mont')->assertDontSee('Québec');
});

it('sauvegarde l\'identification, la comptabilité et les paramètres', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();
    $warehouse = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('name', 'Nouveau nom')
        ->set('phone', '(514)555-1234')
        ->set('address.city', 'Montréal')
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->set('gstNumber', '123456789RT0001')
        ->call('saveAccounting')
        ->assertHasNoErrors()
        ->set('warehouseStoreId', (string) $warehouse->id)
        ->set('shippingWarehouseId', (string) $warehouse->id)
        ->set('isActive', false)
        ->call('saveParameters')
        ->assertHasNoErrors();

    $store->refresh();
    expect($store->name)->toBe('Nouveau nom')
        ->and($store->address_city)->toBe('Montréal')
        ->and($store->gst_number)->toBe('123456789RT0001')
        ->and($store->warehouse_store_id)->toBe($warehouse->id)
        ->and($store->shipping_warehouse_id)->toBe($warehouse->id)
        ->and($store->is_active)->toBeFalse();
});

it('sauvegarde les heures d\'ouverture', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('openingHours.saturday', ['open' => true, 'from' => '10:00', 'to' => '16:00'])
        ->set('openingHours.monday.open', false)
        ->call('saveOpeningHours')
        ->assertHasNoErrors();

    $hours = $store->refresh()->opening_hours;
    expect($hours['saturday'])->toBe(['open' => true, 'from' => '10:00', 'to' => '16:00'])
        ->and($hours['monday']['open'])->toBeFalse();
});

it('refuse une heure de fermeture avant l\'ouverture', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('openingHours.tuesday', ['open' => true, 'from' => '17:00', 'to' => '09:00'])
        ->call('saveOpeningHours')
        ->assertHasErrors(['openingHours.tuesday.to']);
});

it('n\'accepte comme entrepôt qu\'un magasin physique', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();
    $virtual = Store::factory()->virtual()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('warehouseStoreId', (string) $virtual->id)
        ->set('shippingWarehouseId', (string) $virtual->id)
        ->call('saveParameters')
        ->assertHasErrors(['warehouseStoreId', 'shippingWarehouseId']);
});

it('sauvegarde les débuts d\'accumulation et les jours de maladie', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('vacationAccrualMonth', '6')
        ->set('vacationAccrualDay', '1')
        ->set('sickAccrualMonth', '1')
        ->set('sickAccrualDay', '15')
        ->set('sickDaysFullTime', '5')
        ->set('sickDaysPartTime', '3')
        ->call('saveAccounting')
        ->assertHasNoErrors();

    $store->refresh();
    expect($store->vacation_accrual_start)->toBe('06-01')
        ->and($store->sick_accrual_start)->toBe('01-15')
        ->and($store->sick_days_full_time)->toBe(5)
        ->and($store->sick_days_part_time)->toBe(3);

    Livewire::test(Show::class, ['store' => $store])
        ->assertSet('vacationAccrualMonth', '6')
        ->assertSet('sickAccrualDay', '15');
});

it('refuse un jour inexistant ou une date incomplète pour l\'accumulation', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('vacationAccrualMonth', '2')
        ->set('vacationAccrualDay', '30')
        ->set('sickAccrualMonth', '4')
        ->set('sickDaysFullTime', '-1')
        ->call('saveAccounting')
        ->assertHasErrors(['vacationAccrualDay', 'sickAccrualDay', 'sickDaysFullTime']);
});
