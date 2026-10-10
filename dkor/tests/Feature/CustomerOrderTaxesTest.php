<?php

use App\Livewire\CustomerOrders\Index;
use App\Livewire\CustomerOrders\Show;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreTaxRegistration;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\TaxSeeder;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->seed(TaxSeeder::class);

    $this->quebec = Store::factory()->create(['province' => 'QC']);
    $this->ontario = Store::factory()->create(['province' => 'ON']);
});

function sellerAt(Store $store): User
{
    $user = User::factory()->withRole('visiteur')->create(['store_id' => $store->id]);

    return tap($user)->givePermissionTo(['customer_orders.view', 'customer_orders.create', 'customer_orders.edit', 'customers.view']);
}

function createOrderAs(User $user): ?CustomerOrder
{
    test()->actingAs($user);
    $customer = customer::factory()->create();

    Livewire::test(Index::class)->call('create', $customer->id);

    return CustomerOrder::where('customer_id', $customer->id)->first();
}

it('applique les taxes de la province du magasin de l\'usager', function () {
    $quebecOrder = createOrderAs(sellerAt($this->quebec));
    $ontarioOrder = createOrderAs(sellerAt($this->ontario));

    expect($quebecOrder->store_id)->toBe($this->quebec->id)
        ->and($quebecOrder->taxLines->pluck('rate', 'name')->all())->toBe(['TPS' => 5.0, 'TVQ' => 9.975])
        ->and($ontarioOrder->taxLines->pluck('rate', 'name')->all())->toBe(['TVH' => 13.0]);

    stockedLine($ontarioOrder, 1, 1, 100);

    expect($ontarioOrder->fresh())->subtotal->toBe(100.0)->total->toBe(113.0)->balance_due->toBe(113.0)
        ->and($ontarioOrder->taxLines->first()->amount)->toBe(13.0);
});

it('ne retient que les taxes en vigueur à la création de la commande', function () {
    Tax::query()->where('province', 'QC')->where('name', 'TPS')->update(['end_date' => '2020-12-31']);
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'rate' => 4, 'start_date' => '2021-01-01']);

    $order = createOrderAs(sellerAt($this->quebec));

    expect($order->taxLines->pluck('rate', 'name')->all())->toEqual(['TPS' => 4.0, 'TVQ' => 9.975]);
});

it('garde les taux de la commande quand une taxe change ensuite', function () {
    $order = createOrderAs(sellerAt($this->quebec));
    stockedLine($order, 1, 1, 100);

    expect($order->fresh()->total)->toBe(114.98);

    // La TPS passe à 6 % après la création de la commande.
    Tax::query()->where('name', 'TPS')->update(['end_date' => now()->toDateString()]);
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'rate' => 6, 'start_date' => now()->addDay()->toDateString()]);

    stockedLine($order->fresh(), 1, 1, 100);

    expect($order->fresh())->subtotal->toBe(200.0)->total->toBe(229.95)
        ->and($order->fresh()->taxLines->pluck('rate', 'name')->all())->toBe(['TPS' => 5.0, 'TVQ' => 9.975]);
});

it('calcule une taxe en cascade sur le montant plus les taxes individuelles', function () {
    $store = Store::factory()->create(['province' => 'NB']);
    Tax::factory()->create(['province' => 'NB', 'name' => 'Fédérale', 'rate' => 5]);
    Tax::factory()->create(['province' => 'NB', 'name' => 'Provinciale', 'rate' => 10, 'is_compound' => true]);

    $order = CustomerOrder::factory()->create(['store_id' => $store->id]);
    stockedLine($order, 1, 1, 100);

    // 5 $ + 10 % de 105 $ (10,50 $) = 115,50 $
    expect($order->fresh()->total)->toBe(115.5)
        ->and($order->taxLines->pluck('amount', 'name')->all())->toBe(['Fédérale' => 5.0, 'Provinciale' => 10.5]);
});

it('copie les numéros de taxe du marchand sur la commande', function () {
    StoreTaxRegistration::factory()->create(['store_id' => $this->quebec->id, 'tax_name' => 'TPS', 'number' => '123456789RT0001']);

    $order = createOrderAs(sellerAt($this->quebec));

    expect($order->taxLines->pluck('registration_number', 'name')->all())->toBe(['TPS' => '123456789RT0001', 'TVQ' => null]);

    // Un numéro saisi après coup apparaît quand même sur la commande.
    StoreTaxRegistration::factory()->create(['store_id' => $this->quebec->id, 'tax_name' => 'TVQ', 'number' => '1234567890TQ0001']);

    $tvq = $order->fresh()->taxLines->firstWhere('name', 'TVQ');
    expect($tvq->registrationNumber())->toBe('1234567890TQ0001');

    Livewire::test(Show::class, ['order' => $order])
        ->assertSee('123456789RT0001')
        ->assertSee('1234567890TQ0001');
});

it('bloque la création d\'une commande quand l\'usager n\'a pas de magasin', function () {
    $user = sellerAt($this->quebec);
    $user->update(['store_id' => null]);

    expect(createOrderAs($user))->toBeNull()
        ->and(CustomerOrder::count())->toBe(0);
});

it('bloque la création d\'une commande quand aucune taxe n\'est en vigueur dans la province du magasin', function () {
    $user = sellerAt(Store::factory()->create(['province' => 'AB']));

    expect(createOrderAs($user))->toBeNull()
        ->and(CustomerOrder::count())->toBe(0);
});

it('lève une exception explicite pour le magasin sans taxe', function () {
    CustomerOrder::factory()->create(['store_id' => Store::factory()->create(['province' => 'AB'])->id]);
})->throws(DomainException::class, 'Aucune taxe');
