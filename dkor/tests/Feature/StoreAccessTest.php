<?php

use App\Livewire\CustomerOrders\Index as CustomerOrdersIndex;
use App\Livewire\Deliveries\Index as DeliveriesIndex;
use App\Livewire\Inventory\Adjustments;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\TaxSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

/*
 * Magasin d'appartenance (ADR 0001) : sans stores.all, l'usager ne voit et n'agit que pour son magasin.
 */

beforeEach(function () {
    $this->seed(TaxSeeder::class);
    $this->rimouski = Store::factory()->create(['name' => 'Rimouski']);
    $this->gaspe = Store::factory()->create(['name' => 'Gaspé']);
    $this->owner = User::factory()->withRole('owner')->create(['store_id' => $this->rimouski->id]);
    $this->manager = User::factory()->withRole('manager')->create(['store_id' => $this->rimouski->id]);
});

/** @return array<int, int> */
function listedOrderIds(LengthAwarePaginator $orders): array
{
    return $orders->getCollection()->modelKeys();
}

describe('commandes clients', function () {
    beforeEach(function () {
        $this->mine = CustomerOrder::factory()->create(['store_id' => $this->rimouski->id]);
        $this->other = CustomerOrder::factory()->create(['store_id' => $this->gaspe->id]);
    });

    it('montre au vendeur seulement les commandes de son magasin, sans filtre de magasin', function () {
        $this->actingAs(User::factory()->withRole('salesman')->create(['store_id' => $this->rimouski->id]));

        Livewire::test(CustomerOrdersIndex::class)
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [$this->mine->id])
            ->assertDontSee('Tous les magasins');
    });

    it('refuse au vendeur la commande d\'un autre magasin', function () {
        $this->actingAs(User::factory()->withRole('salesman')->create(['store_id' => $this->rimouski->id]));

        $this->get(route('customer-orders.show', $this->mine))->assertOk();
        $this->get(route('customer-orders.show', $this->other))->assertForbidden();
    });

    it('laisse le propriétaire voir et filtrer les commandes de tous les magasins', function () {
        $this->actingAs($this->owner);

        $this->get(route('customer-orders.show', $this->other))->assertOk();

        Livewire::test(CustomerOrdersIndex::class)
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [$this->other->id, $this->mine->id])
            ->set('storeFilter', (string) $this->gaspe->id)
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [$this->other->id]);
    });

    it('crée la commande dans le magasin choisi par le propriétaire', function () {
        $this->actingAs($this->owner);
        $customer = customer::factory()->create();

        Livewire::test(CustomerOrdersIndex::class)
            ->call('openCreate')
            ->assertSet('createStoreId', (string) $this->rimouski->id)
            ->set('createStoreId', (string) $this->gaspe->id)
            ->call('create', $customer->id);

        expect(CustomerOrder::where('customer_id', $customer->id)->sole()->store_id)->toBe($this->gaspe->id);
    });

    it('crée toujours la commande du vendeur dans son magasin', function () {
        $this->actingAs(User::factory()->withRole('salesman')->create(['store_id' => $this->rimouski->id]));
        $customer = customer::factory()->create();

        Livewire::test(CustomerOrdersIndex::class)
            ->set('createStoreId', (string) $this->gaspe->id)
            ->call('create', $customer->id);

        expect(CustomerOrder::where('customer_id', $customer->id)->sole()->store_id)->toBe($this->rimouski->id);
    });
});

describe('livraisons', function () {
    it('ramène le directeur à son magasin s\'il en demande un autre', function () {
        $this->actingAs($this->manager);

        Livewire::withQueryParams(['store' => $this->gaspe->id])
            ->test(DeliveriesIndex::class)
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->set('storeId', (string) $this->gaspe->id)
            ->assertSet('storeId', (string) $this->rimouski->id);
    });

    it('laisse le propriétaire choisir le magasin', function () {
        $this->actingAs($this->owner);

        Livewire::test(DeliveriesIndex::class)
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->set('storeId', (string) $this->gaspe->id)
            ->assertSet('storeId', (string) $this->gaspe->id);
    });
});

describe('inventaire', function () {
    it('refuse un ajustement dans un autre magasin que le sien', function () {
        $this->manager->givePermissionTo('inventory.adjust');
        $this->actingAs($this->manager);

        Livewire::test(Adjustments::class)
            ->call('openAdjust')
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->call('selectProduct', Product::factory()->create()->id)
            ->set('note', 'Brisé')
            ->set('storeId', (string) $this->gaspe->id)
            ->call('adjust')
            ->assertHasErrors(['storeId']);
    });

    it('refuse la prise d\'inventaire d\'un autre magasin', function () {
        $count = InventoryCount::start(today(), $this->owner, $this->gaspe->id);

        $this->actingAs($this->manager)->get(route('inventory.counts.show', $count))->assertForbidden();
        $this->actingAs($this->owner)->get(route('inventory.counts.show', $count))->assertOk();
    });
});
