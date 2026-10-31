<?php

use App\Enums\CustomerOrderStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SupplierType;
use App\Livewire\CustomerOrders\Index as CustomerOrdersIndex;
use App\Livewire\Deliveries\Index as DeliveriesIndex;
use App\Livewire\Inventory\Adjustments;
use App\Livewire\Orders\Index as SupplierOrdersIndex;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\ScheduleEdit;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use Database\Seeders\TaxSeeder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Magasin d'appartenance (ADR 0013) : sans stores.all, l'usager ne voit et n'agit que pour son magasin.
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
            ->set('statusFilter', CustomerOrderStatus::New->value)
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
            ->set('statusFilter', CustomerOrderStatus::New->value)
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

describe('horaires', function () {
    beforeEach(function () {
        $this->manager->givePermissionTo(['schedule_management.view', 'schedule_management.edit', 'schedule_management.publish']);
        $this->colleague = User::factory()->create(['firstname' => 'Cora', 'lastname' => 'Collegue', 'store_id' => $this->rimouski->id]);
        $this->elsewhere = User::factory()->create(['firstname' => 'Elie', 'lastname' => 'Ailleurs', 'store_id' => $this->gaspe->id]);
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
    });

    it('montre au directeur seulement les employés de son magasin, sans sélecteur', function () {
        $this->actingAs($this->manager);

        Livewire::withQueryParams(['store' => $this->gaspe->id])
            ->test(ScheduleEdit::class)
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->assertSee($this->colleague->fullName())
            ->assertDontSee($this->elsewhere->fullName())
            ->assertDontSee('Gaspé');
    });

    it('refuse au directeur l\'horaire d\'un employé d\'un autre magasin', function () {
        $this->actingAs($this->manager);

        Livewire::test(ScheduleEdit::class)
            ->call('openCell', $this->elsewhere->id, $this->weekStart)
            ->assertForbidden();
    });

    it('publie seulement la semaine du magasin choisi par le propriétaire', function () {
        $mine = Schedule::factory()->forDate($this->weekStart)->create(['user_id' => $this->colleague->id]);
        $other = Schedule::factory()->forDate($this->weekStart)->create(['user_id' => $this->elsewhere->id]);

        $this->actingAs($this->owner);

        Livewire::test(ScheduleEdit::class)
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->set('storeId', (string) $this->gaspe->id)
            ->assertSee($this->elsewhere->fullName())
            ->assertDontSee($this->colleague->fullName())
            ->call('publishWeek');

        expect($other->fresh()->status)->toBe(ScheduleStatus::Published)
            ->and($mine->fresh()->status)->toBe(ScheduleStatus::Draft);
    });
});

describe('rendez-vous', function () {
    beforeEach(function () {
        $this->colleague = User::factory()->create(['firstname' => 'Cora', 'lastname' => 'Collegue', 'store_id' => $this->rimouski->id]);
        $this->elsewhere = User::factory()->create(['firstname' => 'Elie', 'lastname' => 'Ailleurs', 'store_id' => $this->gaspe->id]);
    });

    it('n\'offre au vendeur que les employés de son magasin, sans sélecteur de magasin', function () {
        $this->actingAs(User::factory()->withRole('salesman')->create(['store_id' => $this->rimouski->id]));

        Livewire::test(Appointments::class)
            ->assertViewHas('users', fn ($users): bool => ! $users->contains('id', $this->elsewhere->id) && $users->contains('id', $this->colleague->id))
            ->assertDontSee('Gaspé')
            ->set('selectedUserId', $this->elsewhere->id)
            ->assertNotSet('selectedUserId', $this->elsewhere->id);
    });

    it('laisse le propriétaire choisir le magasin, puis un employé de ce magasin', function () {
        $this->actingAs($this->owner);

        Livewire::test(Appointments::class)
            ->set('storeId', (string) $this->gaspe->id)
            ->assertSet('selectedUserId', $this->elsewhere->id)
            ->assertViewHas('users', fn ($users): bool => $users->modelKeys() === [$this->elsewhere->id]);
    });
});

describe('commandes fournisseurs', function () {
    beforeEach(function () {
        $this->supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
        $this->clerk = User::factory()->withRole('warehouse')->create(['store_id' => $this->rimouski->id]);
        $this->clerk->givePermissionTo('supplier_orders.create');
    });

    it('montre au commis seulement les commandes destinées à son magasin', function () {
        $mine = SupplierOrder::factory()->create(['store_id' => $this->rimouski->id]);
        $other = SupplierOrder::factory()->create(['store_id' => $this->gaspe->id]);

        $this->actingAs($this->clerk);

        Livewire::test(SupplierOrdersIndex::class)
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [$mine->id])
            ->assertDontSee('Tous les magasins');

        $this->get(route('supplier-orders.show', $mine))->assertOk();
        $this->get(route('supplier-orders.show', $other))->assertForbidden();
    });

    it('refuse au commis une commande pour un autre magasin', function () {
        $this->actingAs($this->clerk);

        Livewire::test(SupplierOrdersIndex::class)
            ->call('openCreate')
            ->assertSet('newStoreId', (string) $this->rimouski->id)
            ->set('newSupplierId', (string) $this->supplier->id)
            ->set('newStoreId', (string) $this->gaspe->id)
            ->call('create')
            ->assertHasErrors(['newStoreId' => 'in']);

        expect(SupplierOrder::count())->toBe(0);
    });

    it('laisse le propriétaire créer et filtrer les commandes du magasin de son choix', function () {
        $this->actingAs($this->owner);

        Livewire::test(SupplierOrdersIndex::class)
            ->set('storeFilter', (string) $this->gaspe->id)
            ->call('openCreate')
            ->assertSet('newStoreId', (string) $this->gaspe->id)
            ->set('newSupplierId', (string) $this->supplier->id)
            ->call('create')
            ->assertHasNoErrors();

        $order = SupplierOrder::sole();
        expect($order->store_id)->toBe($this->gaspe->id);

        Livewire::test(SupplierOrdersIndex::class)
            ->set('storeFilter', (string) $this->rimouski->id)
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [])
            ->set('storeFilter', '')
            ->assertViewHas('orders', fn (LengthAwarePaginator $orders): bool => listedOrderIds($orders) === [$order->id]);
    });

    it('vérifie le brouillon unique dans le magasin choisi', function () {
        SupplierOrder::factory()->create(['supplier_id' => $this->supplier->id, 'store_id' => $this->gaspe->id]);

        $this->actingAs($this->owner);

        Livewire::test(SupplierOrdersIndex::class)
            ->call('openCreate')
            ->set('newSupplierId', (string) $this->supplier->id)
            ->set('newStoreId', (string) $this->gaspe->id)
            ->call('create')
            ->assertHasErrors('newSupplierId')
            ->set('newStoreId', (string) $this->rimouski->id)
            ->call('create')
            ->assertHasNoErrors();

        expect(SupplierOrder::where('store_id', $this->rimouski->id)->count())->toBe(1);
    });
});
