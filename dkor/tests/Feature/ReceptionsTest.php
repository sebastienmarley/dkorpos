<?php

use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\Orders\Show;
use App\Livewire\Receptions\Create;
use App\Livewire\Receptions\Index;
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
            ->forProduct(Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => $cost]))
            ->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => $cost]);
    }

    $order->markPending();
    $order->send();

    return [$order->fresh(), $lines];
}

it('enregistre une réception partielle: inventaire, ligne de commande et statut', function () {
    [$order, [$line]] = sentOrder(quantities: [10]);

    $reception = Reception::record($order->supplier, [$line->id => ['quantity' => 4, 'unit_cost' => 99]], ['reference' => 'BL-1', 'received_by' => auth()->id()]);

    $stock = InventoryStock::where('product_id', $line->product_id)->first();
    expect($reception->number)->toBe('RC-'.str_pad((string) $reception->id, 6, '0', STR_PAD_LEFT))
        ->and($reception->reference)->toBe('BL-1')
        ->and($reception->lines)->toHaveCount(1)
        ->and($reception->lines->first())->quantity->toBe(4)->unit_cost->toBe(5.0)->supplier_order_line_id->toBe($line->id)
        ->and($reception->total)->toBe(20.0)
        ->and($line->fresh())->quantity_received->toBe(4)->unit_cost->toBe(5.0)
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
        ->and($lineA->fresh()->unit_cost)->toBe(5.0);
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
it('coche une ligne pour proposer la quantité restante, sans coût', function () {
    [$order, [$line]] = sentOrder(quantities: [6], cost: 7.25);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set("selected.{$line->id}", true)
        ->assertSet("quantities.{$line->id}", '6')
        ->assertDontSee('7.25 $')
        ->set("selected.{$line->id}", false)
        ->assertSet('quantities', []);
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
        ->set("quantities.{$lineA->id}", '3')
        ->set('orderSearch', $orderB->number)
        ->set("selected.{$lineB->id}", true)
        ->assertViewHas('selectedLines', fn ($lines) => $lines->pluck('id')->sort()->values()->all() === collect([$lineA->id, $lineB->id])->sort()->values()->all())
        ->assertSet("quantities.{$lineA->id}", '3')
        ->assertSet("quantities.{$lineB->id}", '5')
        ->call('start')
        ->assertRedirect(route('receptions.show', Reception::first()));

    $reception = Reception::first();
    expect($reception->status)->toBe(ReceptionStatus::InProgress)
        ->and($reception->received_by)->toBe(auth()->id())
        ->and($reception->lines)->toHaveCount(2)
        ->and($reception->lines->pluck('quantity', 'supplier_order_line_id')->all())->toBe([$lineA->id => 3, $lineB->id => 5])
        ->and($lineA->fresh()->quantity_received)->toBe(0)
        ->and(InventoryUnit::count())->toBe(0)
        ->and(InventoryMovement::count())->toBe($movementsBefore)
        ->and($orderA->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});
it('ne commence pas de réception pour une ligne non cochée, même avec une quantité saisie', function () {
    [$order, [$line]] = sentOrder();

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set("quantities.{$line->id}", '4')
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
        ->set("quantities.{$foreignLine->id}", '1')
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
        ->and($reception->fresh()->total)->toBe(0.0);

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
        ->and(InventoryUnit::pluck('cost')->unique()->all())->toBe([5.0]);
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
        ->assertSet("quantities.{$line->id}", '6')
        ->assertSet('selected', [$line->id => true]);
});

it('garde la quantité saisie en recochant une ligne déjà remplie', function () {
    [$order, [$line]] = sentOrder(quantities: [6]);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set("selected.{$line->id}", true)
        ->set("quantities.{$line->id}", '2')
        ->set('selected', [$line->id => true])
        ->assertSet("quantities.{$line->id}", '2');
});

it('ne montre ni ne permet de modifier le coût unitaire pendant une réception', function () {
    [$order, [$line]] = sentOrder(quantities: [6], cost: 7.25);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $order->supplier_id)
        ->set('orderSearch', $order->number)
        ->set("selected.{$line->id}", true)
        ->assertDontSee('7.25 $')
        ->assertDontSee('Coût')
        ->set("quantities.{$line->id}", '3')
        ->call('start');

    $reception = Reception::first();

    Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
        ->assertDontSee('7.25 $')
        ->assertDontSee('Coût')
        ->call('complete');

    expect($reception->fresh()->lines->first()->unit_cost)->toBe(7.25);

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
        ->assertSee('Réception en cours')
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
        ->assertSet("quantities.{$receptionLineA->id}", '5')
        ->set("quantities.{$receptionLineA->id}", '2')
        ->set('reference', 'BL-9')
        ->call('saveQuantities')
        ->assertHasNoErrors()
        ->call('removeLine', $receptionLineB->id)
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

    foreach (['0', '5'] as $invalid) {
        Livewire::test(App\Livewire\Receptions\Show::class, ['reception' => $reception])
            ->set("quantities.{$receptionLine->id}", $invalid)
            ->call('saveQuantities');

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
