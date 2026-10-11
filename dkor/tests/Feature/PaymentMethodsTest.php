<?php

use App\Livewire\Accounting\PaymentMethods;
use App\Models\CustomerPaymentMethod;
use App\Models\MerchantPaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Support\Money;
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

it('arrondit le comptant au 5 sous près', function (int $amount, int $rounded) {
    expect(CustomerPaymentMethod::roundCash(Money::of($amount)))->toBeMoney($rounded);
})->with([
    [11498, 11500],
    [11497, 11495],
    [11496, 11495],
    [11493, 11495],
    [11492, 11490],
    [11495, 11495],
    [2, 0],
    [3, 5],
]);

it('gère les méthodes marchandes', function () {
    Livewire::test(PaymentMethods::class)
        ->call('openCreateMerchant')
        ->set('merchantName', ' Chèque ')
        ->call('saveMerchant')
        ->assertHasNoErrors();

    $merchant = MerchantPaymentMethod::where('name', 'Chèque')->sole();
    expect($merchant->is_active)->toBeTrue();

    Livewire::test(PaymentMethods::class)
        ->call('openEditMerchant', $merchant->id)
        ->set('merchantName', 'TEF')
        ->call('saveMerchant')
        ->call('toggleMerchantActive', $merchant->id);

    expect($merchant->fresh())->name->toBe('TEF')->is_active->toBeFalse();
});

it('refuse un nom de méthode marchande en double', function () {
    MerchantPaymentMethod::factory()->create(['name' => 'Chèque']);

    Livewire::test(PaymentMethods::class)
        ->call('openCreateMerchant')
        ->set('merchantName', 'Chèque')
        ->call('saveMerchant')
        ->assertHasErrors('merchantName');
});
