<?php

use App\Livewire\Accounting\PaymentMethods;
use App\Models\CustomerPaymentMethod;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

it('crée un mode de paiement', function () {
    Livewire::test(PaymentMethods::class)
        ->call('openCreate')
        ->set('name', '  Visa ')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomerPaymentMethod::where('name', 'Visa')->sole())->is_active->toBeTrue()->code->toBeNull();
});

it('refuse un nom en double', function () {
    CustomerPaymentMethod::factory()->create(['name' => 'Débit']);

    Livewire::test(PaymentMethods::class)
        ->call('openCreate')
        ->set('name', 'Débit')
        ->call('save')
        ->assertHasErrors('name');
});

it('renomme, désactive et réactive un mode de paiement', function () {
    $method = CustomerPaymentMethod::factory()->create(['name' => 'Cheque']);

    Livewire::test(PaymentMethods::class)
        ->call('openEdit', $method->id)
        ->set('name', 'Chèque')
        ->call('save')
        ->call('toggleActive', $method->id);

    expect($method->fresh())->name->toBe('Chèque')->is_active->toBeFalse();

    Livewire::test(PaymentMethods::class)->call('toggleActive', $method->id);

    expect($method->fresh()->is_active)->toBeTrue();
});

it('refuse l\'accès sans permission', function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->actingAs(User::factory()->withRole('visiteur')->create());

    $this->get(route('accounting.payment-methods'))->assertForbidden();

    Livewire::test(PaymentMethods::class)->call('openCreate')->assertForbidden();
});

it('crée le mode Comptant géré par le système', function () {
    expect(CustomerPaymentMethod::where('code', CustomerPaymentMethod::CASH)->sole())
        ->name->toBe('Comptant')
        ->is_active->toBeTrue();
});

it('ne permet pas de modifier ni de désactiver le mode Comptant', function () {
    $cash = CustomerPaymentMethod::cash();

    Livewire::test(PaymentMethods::class)
        ->assertSee('Géré par le système')
        ->assertDontSeeHtml('wire:click="openEdit('.$cash->id.')"')
        ->call('openEdit', $cash->id)
        ->assertForbidden();

    Livewire::test(PaymentMethods::class)->call('toggleActive', $cash->id)->assertForbidden();

    expect($cash->fresh())->name->toBe('Comptant')->is_active->toBeTrue();
});

it('refuse de créer un autre mode portant le nom réservé', function () {
    Livewire::test(PaymentMethods::class)
        ->call('openCreate')
        ->set('name', 'COMPTANT')
        ->call('save')
        ->assertHasErrors('name');
});

it('arrondit le comptant au 5 sous près', function (float $amount, float $rounded) {
    expect(CustomerPaymentMethod::roundCash($amount))->toBe($rounded);
})->with([
    [114.98, 115.00],
    [114.97, 114.95],
    [114.96, 114.95],
    [114.93, 114.95],
    [114.92, 114.90],
    [114.95, 114.95],
    [0.02, 0.00],
    [0.03, 0.05],
]);
