<?php

use App\Livewire\Accounting\Currencies;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('crée une devise avec un code en majuscules', function () {
    Livewire::test(Currencies::class)
        ->call('openCreate')
        ->set('code', 'gbp')
        ->set('name', 'Livre sterling')
        ->set('rate', '1.72')
        ->call('save')
        ->assertHasNoErrors();

    expect(Currency::where('code', 'GBP')->first())->name->toBe('Livre sterling')->rate->toBe(1.72);
});

it('refuse un code dupliqué ou invalide', function (string $code) {
    Currency::factory()->create(['code' => 'CAD']);

    Livewire::test(Currencies::class)
        ->call('openCreate')
        ->set('code', $code)
        ->set('name', 'Test')
        ->call('save')
        ->assertHasErrors(['code']);
})->with(['cad', 'CA', 'C4D']);

it('modifie une devise existante sans conflit avec son propre code', function () {
    $currency = Currency::factory()->create(['code' => 'CAD', 'name' => 'Dollar']);

    Livewire::test(Currencies::class)
        ->call('openEdit', $currency->id)
        ->set('name', 'Dollar canadien')
        ->call('save')
        ->assertHasNoErrors();

    expect($currency->fresh()->name)->toBe('Dollar canadien');
});

it('assigne une devise à un fournisseur dans la comptabilité', function () {
    $currency = Currency::factory()->create();
    $supplier = Supplier::factory()->create();

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->assertSee($currency->code)
        ->set('currencyId', (string) $currency->id)
        ->call('saveAccounting')
        ->assertHasNoErrors();

    expect($supplier->fresh()->currency_id)->toBe($currency->id);
});

it('refuse une devise inexistante pour un fournisseur', function () {
    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create()])
        ->set('currencyId', '9999')
        ->call('saveAccounting')
        ->assertHasErrors(['currencyId']);
});

it('refuse un taux négatif et affiche le taux dans la table', function () {
    Currency::factory()->create(['code' => 'USD', 'rate' => 1.35]);

    Livewire::test(Currencies::class)
        ->assertSee('1.35')
        ->call('openCreate')
        ->set('code', 'GBP')
        ->set('name', 'Livre')
        ->set('rate', '-1')
        ->call('save')
        ->assertHasErrors(['rate']);
});

it('archive et restaure une devise', function () {
    $currency = Currency::factory()->create();

    $component = Livewire::test(Currencies::class)->call('toggleArchive', $currency->id);
    expect($currency->fresh()->is_archived)->toBeTrue();

    $component->call('toggleArchive', $currency->id);
    expect($currency->fresh()->is_archived)->toBeFalse();
});

it('masque les devises archivées du sélecteur sauf celle du fournisseur', function () {
    $active = Currency::factory()->create(['code' => 'AAA']);
    $archived = Currency::factory()->create(['code' => 'BBB', 'is_archived' => true]);
    $used = Currency::factory()->create(['code' => 'CCC', 'is_archived' => true]);

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->for($used)->create()])
        ->assertSee('AAA')
        ->assertSee('CCC')
        ->assertDontSee('BBB');
});
