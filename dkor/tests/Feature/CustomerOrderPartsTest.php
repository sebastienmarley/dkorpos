<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Part;
use App\Models\Product;
use App\Models\Role;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->withRole('admin')->create();
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->part = Part::factory()->create(['model' => 'PC-12', 'description' => 'Verre de lampe', 'last_cost' => 3]);
});

it('ajoute la pièce choisie dans « Ajouter une pièce » et la commande sans frais au fournisseur', function () {
    Livewire::test(Show::class, ['order' => $this->order])
        ->dispatch('part-selected', id: $this->part->id)
        ->assertSee('PC-12')
        ->assertSee('Verre de lampe');

    $line = $this->order->lines()->sole();
    $supplierLine = $line->supplierOrderLine;

    expect($line)
        ->part_id->toBe($this->part->id)
        ->product_id->toBeNull()
        ->status->toBe(CustomerOrderLineStatus::OnOrder)
        ->quantity_on_order->toBe(1)
        ->quantity_reserved->toBe(0)
        ->unit_price->toBe(0.0)
        ->and($supplierLine)
        ->part_id->toBe($this->part->id)
        ->product_id->toBeNull()
        ->quantity->toBe(1)
        ->unit_cost->toBe(3.0)
        ->description->toContain('PC-12')
        ->description->toContain('commande client #'.$this->order->id)
        ->and($supplierLine->order)
        ->supplier_id->toBe($this->part->supplier_id)
        ->type->toBe(SupplierType::Product)
        ->status->toBe(SupplierOrderStatus::Draft);
});

it('augmente la ligne en commande quand la même pièce est ajoutée de nouveau', function () {
    $this->order->addPart($this->part);
    $this->order->addPart($this->part);

    $line = $this->order->lines()->sole();

    expect($line->quantity)->toBe(2)
        ->and($line->supplierOrderLine->quantity)->toBe(2);
});

it('refuse une pièce dont le fournisseur ne prend pas de commande, sans rien ajouter', function () {
    $this->part->supplier->update(['orderable' => false]);

    expect(fn () => $this->order->addPart($this->part))->toThrow(DomainException::class);

    expect($this->order->lines()->count())->toBe(0)
        ->and(SupplierOrderLine::count())->toBe(0);
});

it('ne prend jamais une pièce en stock', function () {
    $line = $this->order->addPart($this->part);

    expect(fn () => $this->order->updateLine($line, 1, 0, 0, null))->toThrow(DomainException::class);

    expect($line->fresh()->quantity_reserved)->toBe(0);
});

it('change la quantité commandée d\'une pièce en brouillon, et sa ligne fournisseur suit', function () {
    $line = $this->order->addPart($this->part);

    $this->order->updateLine($line, 0, 3, 0, null);

    expect($line->fresh()->quantity)->toBe(3)
        ->and($line->fresh()->supplierOrderLine->quantity)->toBe(3);
});

it('retire une ligne de pièce en brouillon avec sa ligne de commande fournisseur', function () {
    $line = $this->order->addPart($this->part);

    $this->order->removeLine($line);

    expect($this->order->lines()->count())->toBe(0)
        ->and(SupplierOrderLine::count())->toBe(0);
});

it('suit la pièce de la commande à la remise au client, sans mouvement d\'inventaire', function () {
    $line = $this->order->addPart($this->part, 2);
    $supplierLine = $line->supplierOrderLine;
    $supplierOrder = $supplierLine->order;
    $supplierOrder->updateLine($supplierLine, 2, 4.25);

    $supplierOrder->send();

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Ordered);

    $supplierOrder->fresh()->receive([$supplierLine->id => ['quantity' => 2]]);

    expect($line->fresh())
        ->status->toBe(CustomerOrderLineStatus::Received)
        ->quantity_reserved->toBe(2)
        ->quantity_on_order->toBe(0)
        ->and($this->part->fresh()->last_cost)->toBe(4.25);

    $pickup = $this->order->fresh()->pickUp([$line->id => 2], []);

    expect($line->fresh())
        ->status->toBe(CustomerOrderLineStatus::PickedUp)
        ->customer_order_pickup_id->toBe($pickup->id)
        ->delivered_at->not->toBeNull()
        ->and($pickup->handled_by)->toBe($this->user->id);

    expect(InventoryMovement::count())->toBe(0)
        ->and(InventoryUnit::count())->toBe(0);
});

it('refuse la substitution d\'une ligne de pièce chez le fournisseur', function () {
    $supplierLine = $this->order->addPart($this->part)->supplierOrderLine;
    $supplierLine->order->send();
    $product = Product::factory()->create(['supplier_id' => $this->part->supplier_id, 'clean_model' => 'abc']);

    expect(fn () => $supplierLine->fresh()->substituteWith($product))->toThrow(DomainException::class);
});

it('refuse le retour en magasin d\'une pièce remise au client', function () {
    $line = $this->order->addPart($this->part);
    $supplierLine = $line->supplierOrderLine;
    $supplierLine->order->send();
    $supplierLine->order->fresh()->receive([$supplierLine->id => ['quantity' => 1]]);
    $this->order->fresh()->pickUp([$line->id => 1], []);

    expect(fn () => $this->order->fresh()->returnLine($line->fresh(), 1, false))->toThrow(DomainException::class);

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp);
});

it('empêche de supprimer une pièce liée à une commande client', function () {
    $line = $this->order->addPart($this->part);
    $line->supplierOrderLine->delete();

    expect(fn () => $this->part->delete())->toThrow(QueryException::class);

    $this->assertModelExists($this->part);
});

it('empêche de supprimer une pièce liée à une commande fournisseur', function () {
    SupplierOrderLine::factory()->create(['part_id' => $this->part->id]);

    expect(fn () => $this->part->delete())->toThrow(QueryException::class);

    $this->assertModelExists($this->part);
});

it('n\'offre pas « Ajouter une pièce » sans la permission de voir les pièces', function () {
    Role::create(['name' => 'vente_sans_pieces', 'label' => 'Vente sans pièces', 'level' => 0, 'guard_name' => 'web'])
        ->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs(User::factory()->withRole('vente_sans_pieces')->create());

    Livewire::test(Show::class, ['order' => $this->order])
        ->assertDontSee('Ajouter une pièce');
});
