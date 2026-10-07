<?php

use App\Livewire\CustomerOrders\Index;
use App\Models\customer;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customers.view', 'customers.create']);
    $this->actingAs($this->user);
});

it('trouve un client existant et le sélectionne', function () {
    $customer = customer::factory()->create(['firstname' => 'Marguerite', 'lastname' => 'Tremblay']);

    Livewire::test(Index::class)
        ->call('openCustomerModal')
        ->set('customerSearch', 'Margu')
        ->assertSee('Tremblay')
        ->call('selectCustomer', $customer->id)
        ->assertSet('selectedCustomerId', $customer->id)
        ->assertSet('showCustomerModal', false);
});

it('sélectionne le client créé via le formulaire client', function () {
    $customer = customer::factory()->create();

    Livewire::test(Index::class)
        ->dispatch('customer-saved', id: $customer->id)
        ->assertSet('selectedCustomerId', $customer->id);
});

it('permet de retirer le client sélectionné', function () {
    $customer = customer::factory()->create();

    Livewire::test(Index::class)
        ->call('selectCustomer', $customer->id)
        ->call('clearCustomer')
        ->assertSet('selectedCustomerId', null)
        ->assertSet('customerSearch', '');
});
