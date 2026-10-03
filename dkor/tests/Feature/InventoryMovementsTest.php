<?php

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Livewire\Inventory\Movements;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ReceptionLine;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** @return array<int, array{0: ?string, 1: ?string, 2: int, 3: string}> */
function journal(Product $product): array
{
    return InventoryMovement::where('product_id', $product->id)->orderBy('id')->get()
        ->map(fn (InventoryMovement $m) => [$m->from_status?->value, $m->to_status?->value, $m->quantity, $m->type->value])
        ->all();
}

function pendingOrder(int $quantity = 10): array
{
    $order = SupplierOrder::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 5]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => 5]);
    $order->markPending();

    return [$order->fresh(), $product, $line];
}

it('consigne les mouvements du cycle d\'une commande: envoi, réception, renversement', function () {
    [$order, $product, $line] = pendingOrder(10);
    $order->send();
    $reception = $order->fresh()->receive([$line->id => ['quantity' => 6, 'unit_cost' => 5]], auth()->id());
    $reception->lines->first()->reverse(2);

    expect(journal($product))->toBe([
        [null, 'on_order', 10, 'order_placed'],
        ['on_order', 'in_stock', 6, 'receipt'],
        ['in_stock', 'on_order', 2, 'receipt_reversal'],
    ]);

    $movement = InventoryMovement::where('type', InventoryMovementType::Receipt)->first();
    expect($movement->user_id)->toBe(auth()->id())
        ->and($movement->reference)->toBeInstanceOf(ReceptionLine::class)
        ->and(InventoryMovement::where('type', InventoryMovementType::OrderPlaced)->first()->reference->is($line))->toBeTrue();
});

it('consigne les ajouts, changements de quantité, substitution et annulations', function () {
    Mail::fake();
    [$order, $product, $line] = pendingOrder(10);
    $order->send();
    $order = $order->fresh();
    $added = $order->addLine(['product_id' => ($other = Product::factory()->create(['supplier_id' => $order->supplier_id]))->id, 'quantity' => 4, 'unit_cost' => 3]);
    $order->updateLine($line->fresh(), 15, 5);
    $order->updateLine($line->fresh(), 12, 5);
    $replacement = $order->substituteLine($line->fresh(), $sub = Product::factory()->create(['supplier_id' => $order->supplier_id]));
    $added->fresh()->requestCancellation();
    $order->confirmLineCancellation($added->fresh());

    expect(journal($product))->toBe([
        [null, 'on_order', 10, 'order_placed'],
        [null, 'on_order', 5, 'order_line_changed'],
        ['on_order', null, 3, 'order_line_changed'],
        ['on_order', null, 12, 'substitution'],
    ])
        ->and(journal($other))->toBe([
            [null, 'on_order', 4, 'order_line_added'],
            ['on_order', null, 4, 'order_line_cancelled'],
        ])
        ->and(journal($sub))->toBe([[null, 'on_order', 12, 'substitution']]);
});

it('consigne l\'annulation d\'une commande et reste équilibré avec les quantités', function () {
    [$order, $product, $line] = pendingOrder(10);
    $order->send();
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);
    $order->fresh()->cancel();

    expect(journal($product))->toBe([
        [null, 'on_order', 10, 'order_placed'],
        ['on_order', 'in_stock', 4, 'receipt'],
        ['on_order', null, 6, 'order_cancelled'],
    ]);

    $stock = InventoryStock::where('product_id', $product->id)->first();
    foreach (InventoryStatus::cases() as $status) {
        $net = InventoryMovement::where('product_id', $product->id)->where('to_status', $status)->sum('quantity')
            - InventoryMovement::where('product_id', $product->id)->where('from_status', $status)->sum('quantity');

        expect($stock->quantityFor($status))->toBe((int) $net);
    }
});

it('ne consigne rien pour un brouillon ni une ligne de service', function () {
    [$order] = pendingOrder();
    $service = SupplierOrder::factory()->service()->create();
    SupplierOrderLine::factory()->create(['supplier_order_id' => $service->id]);
    $service->markPending();
    $service->send();

    expect(InventoryMovement::count())->toBe(0);
});

it('déplace entre états et refuse un état insuffisant sans rien changer', function () {
    $product = Product::factory()->create();
    InventoryStock::factory()->create([
        'product_id' => $product->id, 'quantity_in_stock' => 5, 'quantity_in_demo' => 0, 'quantity_lost' => 0,
        'quantity_on_order' => 0, 'quantity_reserved' => 0, 'quantity_customer_order' => 0,
    ]);

    InventoryMovement::record($product, InventoryStatus::InStock, InventoryStatus::DefectiveStock, 2, InventoryMovementType::Transfer, note: 'Rayé');

    $stock = InventoryStock::where('product_id', $product->id)->first();
    expect($stock)->quantity_in_stock->toBe(3)->quantity_defective_stock->toBe(2)
        ->and(InventoryMovement::first())->note->toBe('Rayé')->user_id->toBe(auth()->id());

    expect(fn () => InventoryMovement::record($product, InventoryStatus::InStock, InventoryStatus::Lost, 4, InventoryMovementType::Transfer))
        ->toThrow(DomainException::class)
        ->and(fn () => InventoryMovement::record($product, InventoryStatus::InStock, InventoryStatus::InStock, 1, InventoryMovementType::Transfer))
        ->toThrow(DomainException::class)
        ->and(fn () => InventoryMovement::record($product, InventoryStatus::InStock, InventoryStatus::Lost, 0, InventoryMovementType::Transfer))
        ->toThrow(DomainException::class);

    expect($stock->fresh())->quantity_in_stock->toBe(3)->quantity_lost->toBe(0)
        ->and(InventoryMovement::count())->toBe(1);
});

it('gère les neuf états', function () {
    $product = Product::factory()->create();
    InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 9, 'quantity_on_order' => 0, 'quantity_in_demo' => 0, 'quantity_reserved' => 0, 'quantity_customer_order' => 0]);

    foreach (InventoryStatus::cases() as $status) {
        if ($status === InventoryStatus::InStock) {
            continue;
        }

        InventoryMovement::record($product, InventoryStatus::InStock, $status, 1, InventoryMovementType::Transfer);
    }

    $stock = InventoryStock::where('product_id', $product->id)->first();
    expect($stock->quantity_in_stock)->toBe(1)
        ->and($stock->quantityAvailable())->toBe(1);

    foreach (InventoryStatus::cases() as $status) {
        if ($status !== InventoryStatus::InStock) {
            expect($stock->quantityFor($status))->toBe(1);
        }
    }
});

it('ne laisse ni modifier ni supprimer le journal', function () {
    $movement = InventoryMovement::factory()->create();

    expect(fn () => $movement->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('refuse un renversement de réception si le stock n\'est plus « en stock »', function () {
    [$order, $product, $line] = pendingOrder(5);
    $order->send();
    $reception = $order->fresh()->receive([$line->id => ['quantity' => 5, 'unit_cost' => 5]]);
    InventoryMovement::record($product, InventoryStatus::InStock, InventoryStatus::InDemo, 4, InventoryMovementType::Transfer);

    expect(fn () => $reception->lines->first()->reverse(3))->toThrow(DomainException::class)
        ->and($line->fresh()->quantity_received)->toBe(5)
        ->and(InventoryUnit::count())->toBe(5)
        ->and(InventoryMovement::count())->toBe(3);
});

it('affiche, filtre et alimente le journal depuis la page', function () {
    $a = Product::factory()->create(['model' => 'Produit Alpha']);
    $b = Product::factory()->create(['model' => 'Produit Bêta']);
    InventoryStock::factory()->create(['product_id' => $a->id, 'quantity_in_stock' => 5, 'quantity_on_order' => 0, 'quantity_in_demo' => 0, 'quantity_reserved' => 0, 'quantity_customer_order' => 0]);
    InventoryMovement::record($a, InventoryStatus::InStock, InventoryStatus::Lost, 1, InventoryMovementType::Transfer);
    InventoryMovement::factory()->create(['product_id' => $b->id, 'from_status' => InventoryStatus::InStock, 'to_status' => InventoryStatus::InDemo]);

    Livewire::test(Movements::class)
        ->assertSee('Produit Alpha')->assertSee('Produit Bêta')
        ->set('productFilter', (string) $a->id)
        ->assertSee('Produit Alpha')->assertDontSee('Produit Bêta')
        ->set('productFilter', '')
        ->set('statusFilter', 'in_demo')
        ->assertSee('Produit Bêta')->assertDontSee('Produit Alpha');

    Livewire::test(Movements::class)
        ->call('openMove')
        ->set('productSearch', 'Alpha')
        ->call('selectProduct', $a->id)
        ->set('fromStatus', 'in_stock')
        ->set('toStatus', 'defective_stock')
        ->set('quantity', '2')
        ->set('note', 'Fissure')
        ->call('move')
        ->assertHasNoErrors();

    expect(InventoryStock::where('product_id', $a->id)->first())->quantity_defective_stock->toBe(2)->quantity_in_stock->toBe(2);
});

it('refuse dans la page un mouvement manuel vers ou depuis « en commande » ou sans stock suffisant', function () {
    $product = Product::factory()->create();
    InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_on_order' => 5, 'quantity_in_demo' => 0, 'quantity_reserved' => 0, 'quantity_customer_order' => 0]);

    $component = Livewire::test(Movements::class)->call('openMove')->call('selectProduct', $product->id);

    $component->set('fromStatus', 'on_order')->set('toStatus', 'in_stock')->set('quantity', '1')->call('move')->assertHasErrors('fromStatus');
    $component->set('fromStatus', 'in_stock')->set('toStatus', 'in_stock')->call('move')->assertHasErrors('toStatus');
    $component->set('fromStatus', 'in_stock')->set('toStatus', 'lost')->set('quantity', '3')->call('move')->assertHasErrors('quantity');

    expect(InventoryMovement::count())->toBe(0);
});
