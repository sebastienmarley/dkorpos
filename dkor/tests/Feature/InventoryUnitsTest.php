<?php

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierType;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->actingAs(User::factory()->withRole('admin')->create());
    $this->product = Product::factory()->create([
        'supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id,
        'cost' => Money::parse(9),
    ]);
});

/**
 * Commande fournisseur envoyée puis reçue au coût donné (en dollars).
 */
function receiveProduct(Product $product, int $quantity, float $cost): ReceptionLine
{
    $order = SupplierOrder::factory()->create(['supplier_id' => $product->supplier_id]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse($cost)]);
    $order->markPending();
    $order->fresh()->send();

    return Reception::record($order->supplier, [$line->id => ['quantity' => $quantity]])->lines()->sole();
}

/**
 * Commande client qui réserve puis ramasse la quantité donnée du produit, entièrement payée.
 */
function pickUpProduct(Product $product, int $quantity): CustomerOrderLine
{
    $order = CustomerOrder::factory()->create();
    $line = $order->addProduct($product, $quantity);
    $order->pickUp([$line->id => $quantity], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $order->fresh()->balance_due]]);

    return $line->fresh();
}

it('crée les unités au coût de la réception et sort les plus anciennes au ramassage', function () {
    $first = receiveProduct($this->product, 2, 5);
    $this->travel(1)->day();
    receiveProduct($this->product, 2, 7);

    expect(InventoryMovement::where('type', InventoryMovementType::Receipt)->orderBy('id')->get()->map(fn (InventoryMovement $m): int => $m->value->cents())->all())
        ->toBe([1000, 1400]);

    pickUpProduct($this->product, 3);

    $pickup = InventoryMovement::where('type', InventoryMovementType::CustomerPickup)->sole();
    $inHand = InventoryUnit::where('product_id', $this->product->id)->inHand()->sole();

    expect($pickup->value)->toBeMoney(1700)
        ->and($inHand->cost)->toBeMoney(700)
        ->and(InventoryUnit::where('exit_movement_id', $pickup->id)->count())->toBe(3)
        ->and(InventoryUnit::where('reception_line_id', $first->id)->inHand()->count())->toBe(0);

    expectInventoryIntegrity($this->product);
});

it('rouvre au retour les unités sorties par la même commande, défectueux compris', function () {
    receiveProduct($this->product, 1, 5);
    $this->travel(1)->day();
    receiveProduct($this->product, 1, 7);

    $first = pickUpProduct($this->product, 1);
    $this->travel(1)->day();
    $second = pickUpProduct($this->product, 1);

    $first->order->returnLine($first, 1, refund: false);

    $return = InventoryMovement::where('type', InventoryMovementType::CustomerReturn)->sole();

    expect($return->value)->toBeMoney(500)
        ->and(InventoryUnit::inHand()->sole()->cost)->toBeMoney(500);

    $second->order->returnDefective($second, 1, 'Moteur bruyant', replace: false);

    expect(InventoryMovement::where('type', InventoryMovementType::CustomerDefectiveReturn)->sole()->value)->toBeMoney(700)
        ->and(InventoryUnit::inHand()->count())->toBe(2)
        ->and(InventoryUnit::whereNotNull('exit_movement_id')->count())->toBe(0)
        ->and(InventoryStock::where('product_id', $this->product->id)->sole())
        ->quantity_in_stock->toBe(1)
        ->quantity_defective_stock->toBe(1);

    expectInventoryIntegrity($this->product);
});

it('crée les unités des articles endommagés et supprime celles d\'une réception renversée', function () {
    $order = SupplierOrder::factory()->create(['supplier_id' => $this->product->supplier_id]);
    $line = SupplierOrderLine::factory()->forProduct($this->product)->create(['supplier_order_id' => $order->id, 'quantity' => 4, 'unit_cost' => Money::parse(5)]);
    $order->markPending();
    $order->fresh()->send();
    $receptionLine = Reception::record($order->supplier, [$line->id => ['quantity' => 4, 'damaged' => 1]])->lines()->sole();

    InventoryMovement::record($this->product, InventoryStatus::InStock, InventoryStatus::Lost, 1, InventoryMovementType::Transfer);

    expect(InventoryUnit::inHand()->count())->toBe(4)
        ->and(InventoryMovement::where('type', InventoryMovementType::Transfer)->sole()->value)->toBeNull();

    $order->fresh()->reverseReceipt($receptionLine, 1);

    expect(InventoryUnit::inHand()->count())->toBe(3)
        ->and(InventoryMovement::where('type', InventoryMovementType::ReceiptReversal)->sole()->value)->toBeMoney(500);

    expectInventoryIntegrity($this->product);
});

it('valorise au coût du produit le stock sans unité et une entrée sans coût', function () {
    InventoryStock::factory()->create([
        'product_id' => $this->product->id, 'quantity_in_stock' => 2, 'quantity_on_order' => 0, 'quantity_in_demo' => 0,
        'quantity_reserved' => 0, 'quantity_customer_order' => 0,
    ]);

    $exit = InventoryMovement::record($this->product, InventoryStatus::InStock, null, 2, InventoryMovementType::CustomerPickup);
    $entry = InventoryMovement::record($this->product, null, InventoryStatus::InDemo, 1, InventoryMovementType::CustomerReturn);

    expect($exit->value)->toBeMoney(1800)
        ->and($entry->value)->toBeMoney(900)
        ->and(InventoryUnit::inHand()->sole())
        ->cost->toBeMoney(900)
        ->reception_line_id->toBeNull();
});

it('ne change pas les unités pour un mouvement hors des états valorisés', function () {
    receiveProduct($this->product, 2, 5);

    $order = SupplierOrder::factory()->create(['supplier_id' => $this->product->supplier_id]);
    SupplierOrderLine::factory()->forProduct($this->product)->create(['supplier_order_id' => $order->id, 'quantity' => 3, 'unit_cost' => Money::parse(5)]);
    $order->markPending();
    $order->fresh()->send();
    $order->fresh()->cancel();

    expect(InventoryUnit::inHand()->count())->toBe(2)
        ->and(InventoryMovement::whereIn('type', [InventoryMovementType::OrderPlaced, InventoryMovementType::OrderCancelled])->whereNotNull('value')->count())->toBe(0);

    expectInventoryIntegrity($this->product);
});
