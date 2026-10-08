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

    expect(CustomerPaymentMethod::sole())->name->toBe('Visa')->is_active->toBeTrue();
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
