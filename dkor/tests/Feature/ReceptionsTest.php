<?php

use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\Orders\Show;
use App\Livewire\Receptions\Create;
use App\Livewire\Receptions\Index;
use App\Models\CustomerOrderLine;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Commande de produits envoyée avec une ligne par quantité donnée.
 *
 * @param  array<int, int>  $quantities
 * @return array{0: SupplierOrder, 1: array<int, SupplierOrderLine>}
 */
function sentOrder(?Supplier $supplier = null, array $quantities = [10], float $cost = 5.0): array
{
    $order = SupplierOrder::factory()->create(['supplier_id' => $supplier?->id ?? Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $lines = [];

    foreach ($quantities as $quantity) {
        $lines[] = SupplierOrderLine::factory()
            ->forProduct(Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => Money::parse($cost)]))
            ->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse($cost)]);
    }

    $order->markPending();
    $order->send();

    return [$order->fresh(), $lines];
}

/**
 * Commande de produits envoyée avec plusieurs lignes d'un même produit.
 *
 * @param  array<int, int>  $quantities
 * @return array{0: SupplierOrder, 1: array<int, SupplierOrderLine>}
 */
function sameProductOrder(array $quantities): array
{
    $order = SupplierOrder::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $product = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => Money::parse(5)]);
    $lines = array_map(fn (int $quantity): SupplierOrderLine => SupplierOrderLine::factory()
        ->forProduct($product)
        ->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse(5)]), $quantities);

    $order->markPending();
    $order->send();

    return [$order->fresh(), array_map(fn (SupplierOrderLine $line): SupplierOrderLine => $line->fresh(), $lines)];
}

it('enregistre une réception partielle: inventaire, ligne de commande et statut', function () {
    [$order, [$line]] = sentOrder(quantities: [10]);

    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 4, 'unit_cost' => 99]], ['reference' => 'BL-1', 'received_by' => auth()->id()]);

    $stock = InventoryStock::where('product_id', $line->product_id)->first();
    expect($reception->number)->toBe('RC-'.str_pad((string) $reception->id, 6, '0', STR_PAD_LEFT))
        ->and($reception->reference)->toBe('BL-1')
        ->and($reception->lines)->toHaveCount(1)
        ->and($reception->lines->first())->quantity->toBe(4)->unit_cost->toBeMoney(500)->supplier_order_line_id->toBe($line->id)
        ->and($reception->total)->toBeMoney(2000)
        ->and($line->fresh())->quantity_received->toBe(4)->unit_cost->toBeMoney(500)
        ->and($stock)->quantity_in_stock->toBe(4)->quantity_on_order->toBe(6)
        ->and(InventoryUnit::where('product_id', $line->product_id)->count())->toBe(4)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived);
});

it('réceptionne plusieurs commandes du même fournisseur en une seule réception', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [$orderA, [$lineA]] = sentOrder($supplier, [3]);
    [$orderB, [$lineB1, $lineB2]] = sentOrder($supplier, [2, 5]);

    $reception = Reception::record($supplier, [
        $lineA->id => ['quantity' => 3],
        $lineB1->id => ['quantity' => 2],
        $lineB2->id => ['quantity' => 1],
    ]);

    expect($reception->lines)->toHaveCount(3)
        ->and(Reception::count())->toBe(1)
        ->and($orderA->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and($orderB->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived)
        ->and($lineA->fresh()->unit_cost)->toBeMoney(500);
});

it('plafonne la quantité à ce qui reste et ignore les lignes à zéro', function () {
    [$order, [$line, $other]] = sentOrder(quantities: [3, 4]);

    $reception = Reception::record($order->supplier, [
        $line->id => ['quantity' => 99],
        $other->id => ['quantity' => 0],
    ]);

    expect($reception->lines)->toHaveCount(1)
        ->and($reception->lines->first()->quantity)->toBe(3)
        ->and($other->fresh()->quantity_received)->toBe(0);
});

it('refuse une réception vide sans rien créer', function () {
    [$order] = sentOrder();

    expect(fn () => Reception::record($order->supplier, []))->toThrow(DomainException::class)
        ->and(Reception::count())->toBe(0);
});

it('refuse une ligne d\'un autre fournisseur, d\'une commande non envoyée ou de services, sans rien enregistrer', function () {
    [$order, [$line]] = sentOrder();
    [, [$foreignLine]] = sentOrder();
    $pending = SupplierOrder::factory()->create(['supplier_id' => $order->supplier_id]);
    $pendingLine = SupplierOrderLine::factory()->forProduct()->create(['supplier_order_id' => $pending->id]);
    $service = SupplierOrder::factory()->service()->create();
    $serviceLine = SupplierOrderLine::factory()->create(['supplier_order_id' => $service->id]);
    $service->markPending();
    $service->send();

    foreach ([$foreignLine, $pendingLine] as $invalid) {
        expect(fn () => Reception::record($order->supplier, [
            $line->id => ['quantity' => 1],
            $invalid->id => ['quantity' => 1],
        ]))->toThrow(DomainException::class);
    }

    expect(fn () => Reception::record($service->supplier, [$serviceLine->id => ['quantity' => 1]]))->toThrow(DomainException::class)
        ->and(Reception::count())->toBe(0)
        ->and($line->fresh()->quantity_received)->toBe(0)
        ->and(InventoryUnit::count())->toBe(0);
});

it('permet de réceptionner une ligne en demande d\'annulation mais pas une ligne annulée', function () {
    Mail::fake();
    [$order, [$requested, $cancelled]] = sentOrder(quantities: [4, 4]);
    $requested->fresh()->requestCancellation();
    $cancelled->fresh()->requestCancellation();
    $order->fresh()->confirmLineCancellation($cancelled->fresh());

    $reception = Reception::record($order->supplier, [
        $requested->id => ['quantity' => 2],
        $cancelled->id => ['quantity' => 2],
    ]);

    expect($reception->lines)->toHaveCount(1)
        ->and($requested->fresh())->quantity_received->toBe(2)->status->toBe(SupplierOrderLineStatus::CancellationRequested)
        ->and($cancelled->fresh()->quantity_received)->toBe(0);
});

it('garde la réception depuis la page de la commande', function () {
    [$order, [$line]] = sentOrder(quantities: [2]);

    $order->receive([$line->id => ['quantity' => 2, 'unit_cost' => 5]], auth()->id());

    expect(Reception::count())->toBe(1)
        ->and(Reception::first()->received_by)->toBe(auth()->id())
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Received);
});

it('ne montre les lignes qu\'après une recherche de bon de commande du fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [$order, [$line]] = sentOrder($supplier, [6]);
    [$other] = sentOrder($supplier, [2]);
    [$foreign] = sentOrder();
    $done = SupplierOrder::factory()->status(SupplierOrderStatus::Received)->create(['supplier_id' => $supplier->id]);

    Livewire::test(Create::class)
        ->assertViewHas('suppliers', fn ($suppliers) => $suppliers->contains('id', $supplier->id) && $suppliers->contains('id', $foreign->supplier_id))
        ->set('supplierId', (string) $supplier->id)
        ->assertDontSee($order->number)
        ->assertDontSee($other->number)
        ->set('orderSearch', $order->number)
        ->assertSee($order->number)
        ->assertDontSee($other->number)
        ->assertDontSee($done->number)
        ->assertDontSee($foreign->number);

    $order->update(['quote_number' => 'Q-555']);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('orderSearch', 'Q-555')
        ->assertSee($order->number)
        ->assertDontSee($other->number);
});
it('coche et décoche un produit d\'un clic, sans montrer de coût', function () {
    [$order, [$line]] = sentOrder(quantities: [6], cost: 7.25);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set('orderSearch', $order->number)
        ->call('toggleLines', [$line->id])
        ->assertSet("selected.{$line->id}", true)
        ->assertDontSee('7.25 $')
        ->call('toggleLines', [$line->id])
        ->assertSet('selected', []);
});

it('conserve la sélection de plusieurs bons de commande puis commence la réception', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [$orderA, [$lineA]] = sentOrder($supplier, [5]);
    [$orderB, [$lineB]] = sentOrder($supplier, [5]);
    $movementsBefore = InventoryMovement::count();

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('orderSearch', $orderA->number)
        ->set("selected.{$lineA->id}", true)
        ->set('orderSearch', $orderB->number)
        ->set("selected.{$lineB->id}", true)
        ->assertViewHas('selectedLines', fn ($lines) => $lines->pluck('id')->sort()->values()->all() === collect([$lineA->id, $lineB->id])->sort()->values()->all())
        ->call('start')
        ->assertRedirect(route('receptions.show', Reception::first()));

    $reception = Reception::first();
    expect($reception->status)->toBe(ReceptionStatus::InProgress)
        ->and($reception->received_by)->toBe(auth()->id())
        ->and($reception->lines)->toHaveCount(2)
        ->and($reception->lines->pluck('quantity_expected', 'supplier_order_line_id')->all())->toBe([$lineA->id => 5, $lineB->id => 5])
        ->and($reception->lines->pluck('quantity')->all())->toBe([0, 0])
        ->and($reception->lines->whereNotNull('checked_at'))->toBeEmpty()
        ->and($lineA->fresh()->quantity_received)->toBe(0)
        ->and(InventoryUnit::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe($movementsBefore)
        ->and($orderA->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});
it('ne commence pas de réception sans produit coché', function () {
    [$order] = sentOrder();

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->call('start')
        ->assertNoRedirect();

    expect(Reception::count())->toBe(0);
});
it('refuse de commencer une réception avec une ligne forgée d\'un autre fournisseur', function () {
    [$order] = sentOrder();
    [, [$foreignLine]] = sentOrder();

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set("selected.{$foreignLine->id}", true)
        ->assertSet('selected', [])
        ->call('start')
        ->assertNoRedirect();

    expect(Reception::count())->toBe(0);
});
it('liste et recherche les réceptions', function () {
    [$order, [$line]] = sentOrder();
    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 1]], ['reference' => 'BL-777']);
    $other = Reception::factory()->create(['reference' => 'ZZZ-1']);

    Livewire::test(Index::class)
        ->assertSee($reception->number)->assertSee($other->number)
        ->set('search', 'BL-777')
        ->assertSee($reception->number)->assertDontSee($other->number);

    $this->get(route('receptions.show', $reception))->assertOk()->assertSee($reception->number)->assertSee($order->number);
});

it('renverse une réception partielle: unités, stock, en commande, ligne et statut', function () {
    [$order, [$line]] = sentOrder(quantities: [10]);
    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 10, 'unit_cost' => 5]]);
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received);

    $reception->lines->first()->reverse(4, 'Mauvaise livraison', auth()->id());

    $stock = InventoryStock::where('product_id', $line->product_id)->first();
    expect($line->fresh()->quantity_received)->toBe(6)
        ->and($stock)->quantity_in_stock->toBe(6)->quantity_on_order->toBe(4)
        ->and(InventoryUnit::where('product_id', $line->product_id)->count())->toBe(6)
        ->and($reception->lines()->first())
        ->quantity_reversed->toBe(4)->quantity_net->toBe(6)->reversal_reason->toBe('Mauvaise livraison')->reversed_by->toBe(auth()->id())
        ->and($order->fresh())->status->toBe(SupplierOrderStatus::PartiallyReceived)->received_at->toBeNull();
});

it('renverse toute la réception: la ligne redevient non réceptionnée et la commande envoyée', function () {
    [$order, [$line]] = sentOrder(quantities: [5]);
    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 5]]);

    $reception->lines->first()->reverse(5);

    $stock = InventoryStock::where('product_id', $line->product_id)->first();
    expect($line->fresh()->quantity_received)->toBe(0)
        ->and($stock)->quantity_in_stock->toBe(0)->quantity_on_order->toBe(5)
        ->and(InventoryUnit::count())->toBe(0)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Sent)
        ->and($reception->fresh()->total)->toBeMoney(0);

    $second = Reception::record($order->supplier, [$line->id => ['quantity' => 5]]);
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and($second->lines->first()->quantity)->toBe(5);
});

it('ne retire que les unités de la réception renversée', function () {
    [$order, [$line]] = sentOrder(quantities: [10]);
    $first = Reception::record($order->supplier, [$line->id => ['quantity' => 4, 'unit_cost' => 5]]);
    $second = Reception::record($order->supplier, [$line->id => ['quantity' => 6, 'unit_cost' => 6]]);

    $second->lines->first()->reverse(6);

    expect(InventoryUnit::where('reception_line_id', $first->lines->first()->id)->count())->toBe(4)
        ->and(InventoryUnit::where('reception_line_id', $second->lines->first()->id)->count())->toBe(0)
        ->and(InventoryUnit::pluck('cost')->map->cents()->unique()->all())->toBe([500]);
});

it('refuse de renverser plus que la quantité nette ou une quantité invalide', function () {
    [$order, [$line]] = sentOrder(quantities: [5]);
    $receptionLine = Reception::record($order->supplier, [$line->id => ['quantity' => 5]])->lines->first();
    $receptionLine->reverse(3);

    foreach ([0, 3, -1] as $invalid) {
        expect(fn () => $receptionLine->fresh()->reverse($invalid))->toThrow(DomainException::class);
    }

    expect($line->fresh()->quantity_received)->toBe(2);
});

it('refuse de renverser des unités déjà livrées', function () {
    [$order, [$line]] = sentOrder(quantities: [3]);
    $receptionLine = Reception::record($order->supplier, [$line->id => ['quantity' => 3]])->lines->first();
    InventoryUnit::where('reception_line_id', $receptionLine->id)->first()->update(['delivered_at' => today()]);

    expect(fn () => $receptionLine->reverse(3))->toThrow(DomainException::class);

    $receptionLine->reverse(2);

    expect($line->fresh()->quantity_received)->toBe(1)
        ->and(InventoryUnit::count())->toBe(1);
});

it('refuse de renverser une réception une fois facturée', function () {
    [$order, [$line]] = sentOrder(quantities: [3]);
    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 3]]);
    SupplierInvoice::record($reception, ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-03', 'invoice_total' => 15]);

    expect(fn () => $reception->lines->first()->fresh()->reverse(1))->toThrow(DomainException::class)
        ->and($line->fresh()->quantity_received)->toBe(3)
        ->and(InventoryUnit::count())->toBe(3);
});

it('renverse depuis la page de la commande avec la permission', function () {
    [$order, [$line]] = sentOrder(quantities: [4]);
    $receptionLine = Reception::record($order->supplier, [$line->id => ['quantity' => 4]])->lines->first();

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertSee($receptionLine->reception->number)
        ->call('openReverse', $receptionLine->id)
        ->assertSet('reverseQuantity', '4')
        ->set('reverseQuantity', '1')
        ->set('reverseReason', 'Erreur de comptage')
        ->call('reverseReceipt')
        ->assertHasNoErrors();

    expect($line->fresh()->quantity_received)->toBe(3)
        ->and($receptionLine->fresh())->quantity_reversed->toBe(1)->reversal_reason->toBe('Erreur de comptage');
});

it('ne permet pas à un usager sans la permission de renverser', function () {
    [$order, [$line]] = sentOrder(quantities: [4]);
    $receptionLine = Reception::record($order->supplier, [$line->id => ['quantity' => 4]])->lines->first();
    $role = Role::create(['name' => 'sans_renversement', 'label' => 'Sans renversement', 'level' => 0, 'guard_name' => 'web']);
    $role->givePermissionTo(['supplier_orders.view', 'supplier_orders.edit', 'receptions.view']);
    $this->actingAs(User::factory()->withRole('sans_renversement')->create());

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openReverse', $receptionLine->id)
        ->assertForbidden();

    expect($line->fresh()->quantity_received)->toBe(4);
});

it('synchronise les lignes cochées même quand Livewire met à jour tout le tableau', function () {
    [$order, [$line, $other]] = sentOrder(quantities: [6, 3], cost: 7.25);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set('selected', [$line->id => true, $other->id => false])
        ->assertSet('selected', [$line->id => true]);
});

it('regroupe les lignes d\'un même produit et les coche ensemble', function () {
    [$order, [$line, $sameProduct]] = sameProductOrder([6, 2]);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set('orderSearch', $order->number)
        ->assertSee('8 / 8')
        ->call('toggleLines', [$line->id, $sameProduct->id])
        ->assertSet('selected', [$line->id => true, $sameProduct->id => true]);
});

it('ne montre ni ne permet de modifier le coût unitaire pendant une réception', function () {
    [$order, [$line]] = sentOrder(quantities: [6], cost: 7.25);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set('orderSearch', $order->number)
        ->set("selected.{$line->id}", true)
        ->assertDontSee('7.25 $')
        ->assertDontSee('Coût')
        ->call('start');

    $reception = Reception::first();

    Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
        ->assertDontSee('7.25 $')
        ->assertDontSee('Coût')
        ->call('openGroup', 'product-'.$line->product_id)
        ->set('received', '3')
        ->call('saveGroup')
        ->call('complete');

    expect($reception->fresh()->lines->first()->unit_cost)->toBeMoney(725);

    $this->get(route('receptions.show', $reception))->assertOk()->assertDontSee('7.25 $')->assertDontSee('Coût');

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openReceive')
        ->assertDontSee('Coût réel');
});
it('termine une réception en cours: inventaire, commandes, journal et statut', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [$orderA, [$lineA]] = sentOrder($supplier, [5]);
    [$orderB, [$lineB]] = sentOrder($supplier, [2]);
    $reception = Reception::start($supplier, [$lineA->id => ['quantity' => 3], $lineB->id => ['quantity' => 2]], ['received_by' => auth()->id()]);

    expect(InventoryStock::where('product_id', $lineA->product_id)->first()->quantity_in_stock)->toBe(0);

    Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
        ->assertSee('3 / 3')
        ->assertSee('Terminer la réception')
        ->call('complete')
        ->assertHasNoErrors();

    expect($reception->fresh())->status->toBe(ReceptionStatus::Completed)->completed_at->not->toBeNull()
        ->and($lineA->fresh()->quantity_received)->toBe(3)
        ->and(InventoryStock::where('product_id', $lineA->product_id)->first())->quantity_in_stock->toBe(3)->quantity_on_order->toBe(2)
        ->and(InventoryUnit::count())->toBe(5)
        ->and($orderA->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived)
        ->and($orderB->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and(InventoryMovement::where('type', 'receipt')->count())->toBe(2);

    expect(fn () => $reception->fresh()->complete())->toThrow(DomainException::class);
});

it('ajuste les quantités et retire des lignes d\'une réception en cours', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [, [$lineA]] = sentOrder($supplier, [5]);
    [, [$lineB]] = sentOrder($supplier, [4]);
    $reception = Reception::start($supplier, [$lineA->id => ['quantity' => 5], $lineB->id => ['quantity' => 4]]);
    [$receptionLineA, $receptionLineB] = $reception->lines->all();

    Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
        ->call('openGroup', $receptionLineA->groupKey())
        ->assertSet('received', '5')
        ->set('received', '2')
        ->call('saveGroup')
        ->assertHasNoErrors()
        ->call('openGroup', $receptionLineB->groupKey())
        ->call('removeGroup')
        ->set('reference', 'BL-9')
        ->call('complete');

    expect($reception->fresh())->reference->toBe('BL-9')->status->toBe(ReceptionStatus::Completed)
        ->and($reception->fresh()->lines)->toHaveCount(1)
        ->and($lineA->fresh()->quantity_received)->toBe(2)
        ->and($lineB->fresh()->quantity_received)->toBe(0);
});

it('refuse une quantité hors limites dans une réception en cours', function () {
    [$order, [$line]] = sentOrder(quantities: [4]);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 4]]);
    $receptionLine = $reception->lines->first();

    foreach (['-1', '5'] as $invalid) {
        Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
            ->call('openGroup', $receptionLine->groupKey())
            ->set('received', $invalid)
            ->call('saveGroup')
            ->assertHasErrors('received');

        expect($receptionLine->fresh()->quantity)->toBe(4);
    }
});

it('refuse de terminer une réception dont les lignes ont été reçues entre-temps', function () {
    [$order, [$line]] = sentOrder(quantities: [5]);
    $first = Reception::start($order->supplier, [$line->id => ['quantity' => 5]]);
    $second = Reception::start($order->supplier, [$line->id => ['quantity' => 5]]);

    $first->complete();

    expect(fn () => $second->fresh()->complete())->toThrow(DomainException::class)
        ->and($line->fresh()->quantity_received)->toBe(5)
        ->and($second->fresh()->status)->toBe(ReceptionStatus::InProgress)
        ->and(InventoryUnit::count())->toBe(5);
});

it('ne réapplique ni n\'abandonne une réception terminée depuis une instance périmée (deux onglets, double clic)', function () {
    [$order, [$line]] = sentOrder(quantities: [10]);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 4]]);
    $staleComplete = Reception::findOrFail($reception->id);
    $staleDiscard = Reception::findOrFail($reception->id);

    $reception->complete();

    expect(fn () => $staleComplete->complete())->toThrow(DomainException::class, 'Cette réception est déjà terminée.')
        ->and(fn () => $staleDiscard->discard())->toThrow(DomainException::class)
        ->and(Reception::find($reception->id))->not->toBeNull()
        ->and($line->fresh()->quantity_received)->toBe(4)
        ->and(InventoryUnit::count())->toBe(4)
        ->and($line->product->inventoryStocks()->sole()->quantity_in_stock)->toBe(4);

    expectInventoryIntegrity($line->product);
});

it('abandonne une réception en cours sans effet sur l\'inventaire mais pas une réception terminée', function () {
    [$order, [$line]] = sentOrder(quantities: [4]);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 4]]);

    Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
        ->call('discard')
        ->assertRedirect(route('receptions.index'));

    expect(Reception::count())->toBe(0)
        ->and($line->fresh()->quantity_received)->toBe(0);

    $done = Reception::record($order->supplier, [$line->id => ['quantity' => 4]]);

    expect(fn () => $done->discard())->toThrow(DomainException::class)
        ->and(Reception::count())->toBe(1);
});

it('ne renverse pas une réception en cours et ne l\'affiche pas dans la facturation', function () {
    [$order, [$line]] = sentOrder(quantities: [4]);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 4]]);

    expect(fn () => $reception->lines->first()->reverse(1))->toThrow(DomainException::class)
        ->and($order->fresh()->completedReceptionLines()->count())->toBe(0);
});

describe('vérification des produits reçus', function () {
    it('compte un article à chaque scan d\'UPC et vérifie le produit quand tout est reçu', function () {
        [$order, [$line]] = sentOrder(quantities: [2]);
        $line->product->upcs()->create(['upc' => '012345678905']);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'expected' => 2]]);

        $component = Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
            ->assertSee('0 / 2')
            ->set('upcScan', '012345678905')
            ->call('scanUpc')
            ->assertSee('1 / 2');

        expect($reception->lines()->sole())->quantity->toBe(1)->checked_at->toBeNull();

        $component->set('upcScan', '012345678905')->call('scanUpc')->assertSee('2 / 2');

        expect($reception->lines()->sole())->quantity->toBe(2)->checked_at->not->toBeNull();

        $component->set('upcScan', '012345678905')->call('scanUpc');

        expect($reception->lines()->sole()->quantity)->toBe(2);
    });

    it('refuse un UPC inconnu ou d\'un produit qui n\'est pas attendu', function () {
        [$order, [$line]] = sentOrder(quantities: [2]);
        $other = Product::factory()->create(['supplier_id' => $order->supplier_id]);
        $other->upcs()->create(['upc' => '999']);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'expected' => 2]]);

        expect(fn () => $reception->receiveScannedUpc('inconnu'))->toThrow(DomainException::class)
            ->and(fn () => $reception->receiveScannedUpc('999'))->toThrow(DomainException::class, 'n\'est pas attendu');
    });

    it('sert d\'abord les clients et met les manquants sur les lignes pour l\'inventaire', function () {
        [$order, [$stockLine, $customerLine]] = sameProductOrder([3, 2]);
        CustomerOrderLine::factory()->create(['product_id' => $customerLine->product_id, 'supplier_order_line_id' => $customerLine->id, 'quantity' => 2]);
        $reception = Reception::start($order->supplier, [
            $stockLine->id => ['quantity' => 0, 'expected' => 3],
            $customerLine->id => ['quantity' => 0, 'expected' => 2],
        ]);

        $reception->receiveGroup('product-'.$stockLine->product_id, received: 3, damaged: 1, short: 1);

        $lines = $reception->lines()->get()->keyBy('supplier_order_line_id');

        expect($lines[$customerLine->id])->quantity->toBe(2)->quantity_damaged->toBe(0)->quantity_short->toBe(0)->checked_at->not->toBeNull()
            ->and($lines[$stockLine->id])->quantity->toBe(1)->quantity_damaged->toBe(1)->quantity_short->toBe(1);
    });

    it('ne termine pas la réception tant qu\'un produit n\'est pas vérifié', function () {
        [$order, [$line]] = sentOrder(quantities: [4]);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'expected' => 4]]);

        Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
            ->assertSee('Reste 1 produit à vérifier')
            ->call('complete');

        expect($reception->fresh()->status)->toBe(ReceptionStatus::InProgress);

        Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception->fresh()])
            ->call('openGroup', 'product-'.$line->product_id)
            ->set('received', '3')
            ->call('saveGroup')
            ->call('complete');

        expect($reception->fresh()->status)->toBe(ReceptionStatus::Completed)
            ->and($line->fresh())->quantity_received->toBe(3)->quantity_outstanding->toBe(1);
    });
});
