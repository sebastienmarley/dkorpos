<?php

use App\Enums\SupplierType;
use App\Livewire\SupplierForm;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

it('utilise 2 comme base par défaut', function () {
    $supplier = Supplier::factory()->create()->fresh();

    expect($supplier->base_multiplier)->toBe(2.0)
        ->and($supplier->price_multiplier)->toBe(2.0);
});

it('calcule le multiplicateur à partir des quatre composantes', function () {
    $supplier = Supplier::factory()->for(Currency::factory()->create(['rate' => 1.35]))->create([
        'base_multiplier' => 2,
        'customs_fee' => 0.1,
        'shipping_fee' => 0.05,
    ])->fresh();

    expect($supplier->price_multiplier)->toBe(3.5);
});

it('sauvegarde les composantes et recalcule le multiplicateur', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $supplier = Supplier::factory()->for(Currency::factory()->create(['rate' => 1.3]))->create(['type' => SupplierType::Product]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('baseMultiplier', '2')
        ->set('customsFee', '0.2')
        ->set('shippingFee', '0.5')
        ->assertSee('4.0000')
        ->call('saveParameters')
        ->assertHasNoErrors();

    expect($supplier->fresh()->price_multiplier)->toBe(4.0);
});

it('refuse une composante négative', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create(['type' => SupplierType::Product])])
        ->set('shippingFee', '-1')
        ->call('saveParameters')
        ->assertHasErrors(['shippingFee']);
});

it('recalcule le multiplicateur des fournisseurs quand le taux de la devise change', function () {
    $currency = Currency::factory()->create(['rate' => 1.5]);
    $supplier = Supplier::factory()->for($currency)->create(['base_multiplier' => 2, 'customs_fee' => 0, 'shipping_fee' => 0]);

    expect($supplier->fresh()->price_multiplier)->toBe(3.5);

    $currency->update(['rate' => 1.2]);

    expect($supplier->fresh()->price_multiplier)->toBe(3.2);
});

it('recalcule le multiplicateur quand la devise du fournisseur change', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $currency = Currency::factory()->create(['rate' => 1.35]);
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'base_multiplier' => 2, 'customs_fee' => 0, 'shipping_fee' => 0]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('currencyId', (string) $currency->id)
        ->call('saveAccounting')
        ->assertSee('3.3500');

    expect($supplier->fresh()->price_multiplier)->toBe(3.35);
});

it('accepte le type de fournisseur expédition', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(SupplierForm::class)
        ->call('openCreate')
        ->set('type', 'shipping')
        ->set('name', 'Transport Express')
        ->call('save')
        ->assertHasNoErrors();

    expect(Supplier::where('name', 'Transport Express')->first()->type)->toBe(SupplierType::Shipping);

    Livewire::test(SupplierForm::class)
        ->call('openCreate')
        ->set('type', 'bogus')
        ->set('name', 'X')
        ->call('save')
        ->assertHasErrors(['type']);
});

it('exige un montant prepaid sauf si collect est coché', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('collect', false)
        ->set('prepaidAmount', '')
        ->call('saveTransport')
        ->assertHasErrors(['prepaidAmount' => 'required'])
        ->set('prepaidAmount', '-5')
        ->call('saveTransport')
        ->assertHasErrors(['prepaidAmount' => 'min'])
        ->set('prepaidAmount', '125.50')
        ->call('saveTransport')
        ->assertHasNoErrors();

    expect($supplier->fresh())->prepaid_amount->toBe(125.5)->collect->toBeFalse();

    Livewire::test(SupplierShow::class, ['supplier' => $supplier->fresh()])
        ->set('collect', true)
        ->set('prepaidAmount', '')
        ->call('saveTransport')
        ->assertHasNoErrors();

    expect($supplier->fresh())->prepaid_amount->toBeNull()->collect->toBeTrue();
});

it('affiche l\'onglet transport seulement pour les fournisseurs de produits', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create(['type' => SupplierType::Product])])
        ->assertSee('Montant prepaid');

    $service = Supplier::factory()->create(['type' => SupplierType::Service]);

    Livewire::test(SupplierShow::class, ['supplier' => $service])
        ->assertDontSee('Montant prepaid')
        ->call('saveTransport')
        ->assertNotFound();
});

it('enregistre le fournisseur d\'expédition par défaut seulement si collect', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $shipping = Supplier::factory()->create(['type' => SupplierType::Shipping, 'name' => 'Transport Alpha']);
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    $component = Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->assertDontSee('Transport Alpha')
        ->set('collect', true)
        ->assertSee('Transport Alpha')
        ->set('defaultShippingSupplierId', (string) $shipping->id)
        ->call('saveTransport')
        ->assertHasNoErrors();

    expect($supplier->fresh()->default_shipping_supplier_id)->toBe($shipping->id);

    $component->set('collect', false)->set('prepaidAmount', '10')->call('saveTransport');

    expect($supplier->fresh()->default_shipping_supplier_id)->toBeNull();
});

it('refuse un fournisseur par défaut qui n\'est pas de type expédition', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $other = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create(['type' => SupplierType::Product])])
        ->set('collect', true)
        ->set('defaultShippingSupplierId', (string) $other->id)
        ->call('saveTransport')
        ->assertHasErrors(['defaultShippingSupplierId']);
});

it('met commandable à faux quand le fournisseur est désactivé depuis l\'identification', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $supplier = Supplier::factory()->create(['type' => SupplierType::Shipping, 'orderable' => true, 'is_active' => true]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('isActive', false)
        ->call('saveIdentification')
        ->assertHasNoErrors()
        ->assertSet('orderable', false);

    expect($supplier->fresh())->is_active->toBeFalse()->orderable->toBeFalse();
});

it('affiche l\'onglet info commande sauf pour un fournisseur d\'expédition', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create(['type' => SupplierType::Product])])
        ->assertSee('Info commande');

    $shipping = Supplier::factory()->create(['type' => SupplierType::Shipping]);

    Livewire::test(SupplierShow::class, ['supplier' => $shipping])
        ->assertDontSee('Info commande')
        ->call('saveParameters')
        ->assertNotFound();
});
