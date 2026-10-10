<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\DefectiveResolution;
use App\Enums\InventoryMovementType;
use App\Livewire\Orders\Show as SupplierOrderShow;
use App\Livewire\Receptions\Show as ReceptionShow;
use App\Models\CustomerOrder;
use App\Models\DefectiveProduct;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Commande de produits envoyée avec une ligne de la quantité donnée.
 *
 * @return array{0: SupplierOrder, 1: Product, 2: SupplierOrderLine}
 */
function sentOrderWithLine(int $quantity): array
{
    $order = SupplierOrder::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 5]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => 5]);
    $order->send();

    return [$order->fresh(), $product, $line->fresh()];
}

it('reçoit les articles endommagés en inventaire défectueux avec un dossier', function () {
    [$order, $product, $line] = sentOrderWithLine(5);

    $reception = $order->receive([$line->id => ['quantity' => 5, 'damaged' => 2]]);

    $receptionLine = $reception->lines()->sole();

    expect($receptionLine)->quantity->toBe(5)->quantity_damaged->toBe(2)
        ->and($line->fresh()->quantity_received)->toBe(5)
        ->and(InventoryStock::where('product_id', $product->id)->sole())
        ->quantity_in_stock->toBe(3)
        ->quantity_defective_stock->toBe(2)
        ->quantity_on_order->toBe(0)
        ->and(InventoryUnit::where('product_id', $product->id)->count())->toBe(3)
        ->and(InventoryMovement::where('type', InventoryMovementType::ReceiptDamaged)->sole()->quantity)->toBe(2)
        ->and(DefectiveProduct::sole())
        ->resolution->toBe(DefectiveResolution::DamagedOnArrival)
        ->reason->toBe('Endommagé à l\'arrivée')
        ->quantity->toBe(2)
        ->product_id->toBe($product->id)
        ->supplier_order_line_id->toBe($line->id)
        ->reception_line_id->toBe($receptionLine->id)
        ->photo_path->toBeNull();
});

it('refuse une quantité endommagée supérieure à la quantité reçue', function () {
    [$order, , $line] = sentOrderWithLine(5);

    expect(fn () => $order->receive([$line->id => ['quantity' => 2, 'damaged' => 3]]))->toThrow(DomainException::class);

    expect(DefectiveProduct::count())->toBe(0)
        ->and($line->fresh()->quantity_received)->toBe(0);
});

it('ne permet pas de renverser les unités endommagées', function () {
    [$order, $product, $line] = sentOrderWithLine(2);
    $reception = $order->receive([$line->id => ['quantity' => 2, 'damaged' => 1]]);
    $receptionLine = $reception->lines()->sole();

    expect(fn () => $order->fresh()->reverseReceipt($receptionLine, 2))->toThrow(DomainException::class);

    $order->fresh()->reverseReceipt($receptionLine->fresh(), 1);

    expect(InventoryStock::where('product_id', $product->id)->sole())
        ->quantity_in_stock->toBe(0)
        ->quantity_defective_stock->toBe(1)
        ->quantity_on_order->toBe(1);
});

it('saisit les endommagés dans une réception en cours avant de la terminer', function () {
    [$order, $product, $line] = sentOrderWithLine(4);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 4]]);

    Livewire::test(ReceptionShow::class, ['reception' => $reception])
        ->assertSee('dont endommagé')
        ->set('damaged.'.$reception->lines()->sole()->id, '1')
        ->call('saveQuantities')
        ->assertHasNoErrors()
        ->call('complete');

    expect(InventoryStock::where('product_id', $product->id)->sole())
        ->quantity_in_stock->toBe(3)
        ->quantity_defective_stock->toBe(1)
        ->and(DefectiveProduct::sole()->quantity)->toBe(1);
});

it('saisit les endommagés depuis la commande fournisseur', function () {
    [$order, $product, $line] = sentOrderWithLine(3);

    Livewire::test(SupplierOrderShow::class, ['order' => $order])
        ->call('openReceive')
        ->assertSet("receipts.{$line->id}.damaged", '0')
        ->set("receipts.{$line->id}.damaged", '3')
        ->call('receive');

    expect(InventoryStock::where('product_id', $product->id)->sole())
        ->quantity_in_stock->toBe(0)
        ->quantity_defective_stock->toBe(3);
});

describe('ligne liée à un client', function () {
    beforeEach(function () {
        $this->customerOrder = CustomerOrder::factory()->create();
        $this->customerLine = $this->customerOrder->addProduct(Product::factory()->create(), 3);
        $this->supplierOrder = SupplierOrder::sole();
        $this->supplierOrder->send();
        $this->supplierLine = SupplierOrderLine::sole();
    });

    it('réserve au client les articles en bon état et recommande les endommagés', function () {
        $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 3, 'damaged' => 1]]);

        expect($this->customerLine->fresh())
            ->quantity_reserved->toBe(2)
            ->quantity_on_order->toBe(0)
            ->quantity->toBe(2)
            ->status->toBe(CustomerOrderLineStatus::Received);

        $reorder = $this->customerOrder->lines()->whereKeyNot($this->customerLine->id)->sole();

        expect($reorder)
            ->quantity->toBe(1)
            ->quantity_on_order->toBe(1)
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->and($reorder->supplierOrderLine)
            ->quantity->toBe(1)
            ->and($reorder->supplierOrderLine->order->id)->not->toBe($this->supplierOrder->id)
            ->and($this->customerOrder->fresh()->subtotal)->toBe(round(3 * $this->customerLine->unit_price, 2));
    });

    it('recommande toute la ligne quand tout arrive endommagé', function () {
        $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 3, 'damaged' => 3]]);

        $line = $this->customerLine->fresh();

        expect($line)
            ->quantity_on_order->toBe(3)
            ->quantity_reserved->toBe(0)
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->supplier_order_line_id->not->toBe($this->supplierLine->id)
            ->and($line->supplierOrderLine->order->status->isEditable())->toBeTrue()
            ->and($this->customerOrder->lines()->count())->toBe(1);
    });
});
