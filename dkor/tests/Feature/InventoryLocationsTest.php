<?php

use App\Enums\DeliveryPeriod;
use App\Livewire\CustomerOrders\Show as CustomerOrderShow;
use App\Livewire\Deliveries\Mine;
use App\Livewire\LinePlacement;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Receptions\Show as ReceptionShow;
use App\Livewire\Stores\Locations;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryLocation;
use App\Models\InventoryZone;
use App\Models\Reception;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());

    $this->order = CustomerOrder::factory()->create();
    $this->store = $this->order->store;
    $this->zone = InventoryZone::factory()->create(['store_id' => $this->store->id, 'name' => 'Allée A']);
    $this->location = InventoryLocation::factory()->create(['inventory_zone_id' => $this->zone->id, 'code' => 'A-03-2']);
});

describe('onglet « Zones et emplacements » du magasin', function () {
    it('ajoute un emplacement à une zone, avec un code unique dans le magasin', function () {
        $otherStore = Store::factory()->create();
        $otherZone = InventoryZone::factory()->create(['store_id' => $otherStore->id]);

        Livewire::test(Locations::class, ['store' => $this->store])
            ->assertSee('Allée A')
            ->assertSee('A-03-2')
            ->call('openLocation', $this->zone->id)
            ->set('locationCode', 'a-03-2')
            ->call('saveLocation')
            ->assertHasErrors('locationCode')
            ->set('locationCode', 'b-01-1')
            ->set('locationDescription', 'Étagère du haut')
            ->call('saveLocation')
            ->assertHasNoErrors();

        Livewire::test(Locations::class, ['store' => $otherStore])
            ->call('openLocation', $otherZone->id)
            ->set('locationCode', 'A-03-2')
            ->call('saveLocation')
            ->assertHasNoErrors();

        expect(InventoryLocation::where('code', 'B-01-1')->sole())
            ->inventory_zone_id->toBe($this->zone->id)
            ->description->toBe('Étagère du haut');
    });

    it('n\'accepte pas la zone d\'un autre magasin', function () {
        $otherZone = InventoryZone::factory()->create(['store_id' => Store::factory()->create()->id]);

        Livewire::test(Locations::class, ['store' => $this->store])
            ->call('openLocation')
            ->set('locationZoneId', (string) $otherZone->id)
            ->set('locationCode', 'Z-1')
            ->call('saveLocation')
            ->assertHasErrors('locationZoneId');
    });

    it('ne propose plus un emplacement désactivé, mais l\'affiche encore là où il est noté', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        $this->order->placeLine($line, $this->location->id);

        Livewire::test(Locations::class, ['store' => $this->store])->call('toggleLocation', $this->location->id);

        expect(fn () => $this->order->placeLine($line->fresh(), $this->location->id))->toThrow(DomainException::class)
            ->and($line->fresh()->location->code)->toBe('A-03-2');

        Livewire::test(CustomerOrderShow::class, ['order' => $this->order])->assertSee('Emplacement : A-03-2');
    });

    it('mène les superviseurs d\'inventaire à l\'onglet de leur magasin', function () {
        $supervisor = User::factory()->withRole('manager')->create(['store_id' => $this->store->id]);

        $this->actingAs($supervisor)
            ->get(route('inventory.zones'))
            ->assertRedirect(route('stores.show', ['store' => $this->store->id, 'tab' => 'locations']));
    });
});

describe('ranger une ligne de commande client', function () {
    it('choisit l\'emplacement dans la liste, ou le balaie avec la douchette', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        $other = InventoryLocation::factory()->create(['inventory_zone_id' => $this->zone->id, 'code' => 'C-09-1']);

        Livewire::test(LinePlacement::class, ['lineId' => $line->id])
            ->set('locationId', (string) $this->location->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('line-placed');

        expect($line->fresh()->inventory_location_id)->toBe($this->location->id);

        Livewire::test(LinePlacement::class, ['lineId' => $line->id])
            ->set('scan', 'c-09-1')
            ->call('scanned')
            ->assertHasNoErrors()
            ->set('scan', 'X-00')
            ->call('scanned')
            ->assertHasErrors('scan');

        expect($line->fresh()->inventory_location_id)->toBe($other->id);
    });

    it('refuse un emplacement d\'un autre magasin', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        $foreign = InventoryLocation::factory()->create();

        expect(fn () => $this->order->placeLine($line, $foreign->id))
            ->toThrow(DomainException::class, 'Choisissez un emplacement actif du magasin de la commande.');
    });

    it('propose l\'emplacement habituel du produit dans le magasin', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        $line->product->update(['clean_model' => 'MODELE1']);

        Livewire::test(ProductShow::class, ['product' => $line->product->fresh()])
            ->set("habitualLocations.{$this->store->id}", (string) $this->location->id)
            ->call('saveHabitualLocation', $this->store->id)
            ->assertHasNoErrors();

        expect($line->product->habitualLocationFor($this->store->id)?->id)->toBe($this->location->id);

        Livewire::test(LinePlacement::class, ['lineId' => $line->id])
            ->assertSet('locationId', (string) $this->location->id);
    });

    it('n\'accepte pas comme emplacement habituel un emplacement d\'un autre magasin', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        $foreign = InventoryLocation::factory()->create();

        expect(fn () => $line->product->setHabitualLocation($this->store->id, $foreign->id))->toThrow(DomainException::class);
    });

    it('range depuis la réception terminée les articles réservés aux clients', function () {
        $line = stockedLine($this->order, 0, 1, 100);
        $supplierLine = $line->supplierOrderLine;
        $supplierLine->order->markPending();
        $supplierLine->order->send();
        $reception = Reception::start($supplierLine->order->supplier, [$supplierLine->id => ['quantity' => 1]]);
        $reception->complete();

        Livewire::test(ReceptionShow::class, ['reception' => $reception->fresh()])
            ->assertSee('Ranger les articles réservés aux clients')
            ->assertSee('commande #'.$this->order->id)
            ->assertSeeLivewire(LinePlacement::class);
    });

    it('garde l\'emplacement sur la partie séparée d\'une ligne', function () {
        $line = stockedLine($this->order, 2, 2, 100);
        $this->order->placeLine($line, $this->location->id);
        $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);
        $delivery = CustomerOrderDelivery::factory()->on(now()->addDay()->toDateString(), DeliveryPeriod::Am)->create(['customer_order_id' => $this->order->id]);
        $deliveryLine = $delivery->lines()->create(['customer_order_line_id' => $line->id, 'quantity' => 2]);

        $this->order->loadDelivery($delivery, [$deliveryLine->id => 1]);

        expect(CustomerOrderLine::where('inventory_location_id', $this->location->id)->count())->toBe(2);
    });
});

it('fait noter au livreur l\'emplacement d\'un article refusé, et lui montre celui des articles à charger', function () {
    Storage::fake('local');
    $driver = User::factory()->withRole('delivery')->create(['store_id' => $this->store->id]);
    $line = stockedLine($this->order, 1, 1, 100);
    $this->order->placeLine($line, $this->location->id);
    $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);
    $delivery = CustomerOrderDelivery::factory()->on(today()->toDateString(), DeliveryPeriod::Pm)->create(['customer_order_id' => $this->order->id]);
    $deliveryLine = $delivery->lines()->create(['customer_order_line_id' => $line->id, 'quantity' => 1]);
    $returnShelf = InventoryLocation::factory()->create(['inventory_zone_id' => $this->zone->id, 'code' => 'RET-1']);

    $this->actingAs($driver);

    Livewire::withQueryParams(['date' => today()->toDateString()])
        ->test(Mine::class)
        ->call('open', $delivery->id)
        ->assertSee('Emplacement : A-03-2')
        ->call('depart')
        ->call('open', $delivery->id)
        ->set('completionStep', 'refused')
        ->set("refusalReasons.{$deliveryLine->id}", 'Endommagé')
        ->set("returnLocations.{$deliveryLine->id}", (string) $returnShelf->id)
        ->call('markUndelivered', 'all_refused')
        ->assertHasNoErrors();

    expect($line->fresh()->inventory_location_id)->toBe($returnShelf->id);
});
