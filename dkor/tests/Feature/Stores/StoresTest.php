<?php

use App\Enums\Province;
use App\Enums\StoreType;
use App\Livewire\StoreForm;
use App\Livewire\Stores\Index;
use App\Livewire\Stores\Show;
use App\Models\Store;
use App\Models\StoreTaxRegistration;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxSeeder;
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
        ->set('province', 'ON')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('store-saved');

    $store = Store::firstWhere('name', 'Boutique en ligne');
    expect($store->type)->toBe(StoreType::Virtual)
        ->and($store->province)->toBe(Province::Ontario)
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
    Store::factory()->create(['name' => 'Gatineau', 'is_active' => false]);

    Livewire::test(Index::class)->assertSee('Montréal')->assertDontSee('Gatineau')
        ->set('showInactive', true)->assertSee('Gatineau')
        ->set('search', 'Mont')->assertDontSee('Gatineau');
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

it('sauvegarde les frais d\'annulation du magasin', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->assertSet('cancellationFeePercent', '0')
        ->set('cancellationFeePercent', '15.5')
        ->call('saveAccounting')
        ->assertHasNoErrors();

    expect($store->fresh()->cancellation_fee_percent)->toBe(15.5);

    Livewire::test(Show::class, ['store' => $store->fresh()])
        ->assertSet('cancellationFeePercent', '15.5')
        ->set('cancellationFeePercent', '120')
        ->call('saveAccounting')
        ->assertHasErrors('cancellationFeePercent');
});

it('exige une province valide à la création d\'un magasin', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());

    Livewire::test(StoreForm::class)
        ->set('name', 'Boutique')
        ->set('province', 'XX')
        ->call('save')
        ->assertHasErrors('province');
});

it('exige une province valide dans l\'identification', function () {
    $this->actingAs(User::factory()->withRole('owner')->create());

    Livewire::test(Show::class, ['store' => Store::factory()->create()])
        ->set('province', '')
        ->call('saveIdentification')
        ->assertHasErrors('province');
});

function seedProvincialTaxes(): void
{
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'start_date' => '2008-01-01']);
    Tax::factory()->create(['province' => 'QC', 'name' => 'TVQ', 'start_date' => '2013-01-01']);
    Tax::factory()->create(['province' => 'ON', 'name' => 'TVH', 'start_date' => '2010-07-01']);
}

it('affiche un numéro par taxe en vigueur dans la province du magasin', function () {
    seedProvincialTaxes();
    $this->actingAs(User::factory()->withRole('owner')->create());
    StoreTaxRegistration::factory()->create(['store_id' => ($store = Store::factory()->create())->id, 'tax_name' => 'TPS', 'number' => '111RT0001']);

    Livewire::test(Show::class, ['store' => $store])
        ->assertSet('taxNumbers', [['name' => 'TPS', 'number' => '111RT0001'], ['name' => 'TVQ', 'number' => '']])
        ->assertSee('Numéro de TPS')
        ->assertSee('Numéro de TVQ')
        ->assertDontSee('Numéro de TVH');

    $ontario = Store::factory()->create(['province' => 'ON']);

    Livewire::test(Show::class, ['store' => $ontario])
        ->assertSet('taxNumbers', [['name' => 'TVH', 'number' => '']])
        ->assertSee('Numéro de TVH')
        ->assertDontSee('Numéro de TPS');
});

it('ignore les taxes expirées ou à venir dans les numéros du magasin', function () {
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'start_date' => '2008-01-01']);
    Tax::factory()->create(['province' => 'QC', 'name' => 'Ancienne', 'start_date' => '2000-01-01', 'end_date' => '2007-12-31']);
    Tax::factory()->create(['province' => 'QC', 'name' => 'Future', 'start_date' => '2099-01-01']);

    expect(Store::factory()->create()->applicableTaxNames()->all())->toBe(['TPS']);
});

it('sauvegarde, modifie et efface les numéros de taxe', function () {
    seedProvincialTaxes();
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('taxNumbers.0.number', '123456789RT0001')
        ->set('taxNumbers.1.number', '1234567890TQ0001')
        ->call('saveAccounting')
        ->assertHasNoErrors();

    expect($store->taxRegistrations()->pluck('number', 'tax_name')->all())
        ->toBe(['TPS' => '123456789RT0001', 'TVQ' => '1234567890TQ0001']);

    Livewire::test(Show::class, ['store' => $store->fresh()])
        ->assertSet('taxNumbers.0.number', '123456789RT0001')
        ->set('taxNumbers.0.number', '')
        ->set('taxNumbers.1.number', '999TQ0001')
        ->call('saveAccounting');

    expect($store->taxRegistrations()->pluck('number', 'tax_name')->all())->toBe(['TVQ' => '999TQ0001']);
});

it('conserve les numéros d\'une autre province quand la province change', function () {
    seedProvincialTaxes();
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    $component = Livewire::test(Show::class, ['store' => $store])
        ->set('taxNumbers.0.number', '111RT0001')
        ->call('saveAccounting')
        ->set('province', 'ON')
        ->call('saveIdentification')
        ->assertSet('taxNumbers', [['name' => 'TVH', 'number' => '']])
        ->set('taxNumbers.0.number', '222RT0001')
        ->call('saveAccounting');

    expect($store->taxRegistrations()->pluck('number', 'tax_name')->all())->toBe(['TPS' => '111RT0001', 'TVH' => '222RT0001']);

    $component->set('province', 'QC')
        ->call('saveIdentification')
        ->assertSet('taxNumbers.0.number', '111RT0001');
});

it('ne sauvegarde pas le numéro d\'une taxe étrangère à la province du magasin', function () {
    seedProvincialTaxes();
    $this->actingAs(User::factory()->withRole('owner')->create());
    $store = Store::factory()->create();

    Livewire::test(Show::class, ['store' => $store])
        ->set('taxNumbers', [['name' => 'TVH', 'number' => '123']])
        ->call('saveAccounting');

    expect($store->taxRegistrations()->count())->toBe(0);
});

it('sème les taxes de départ sans doublon', function () {
    $this->seed(TaxSeeder::class);
    $this->seed(TaxSeeder::class);

    expect(Tax::count())->toBe(3)
        ->and(Tax::activeOn('2026-01-01')->where('province', 'ON')->sole())->name->toBe('TVH')->rate->toBe('13.000');
});
