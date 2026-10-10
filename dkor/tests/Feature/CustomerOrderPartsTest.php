<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Part;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
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
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'base_multiplier' => 2.5]);
    $this->part = Part::factory()->create(['supplier_id' => $supplier->id, 'model' => 'PC-12', 'description' => 'Verre de lampe', 'last_cost' => 3]);
});

it('ajoute la pièce choisie dans « Ajouter une pièce » et la commande au fournisseur', function () {
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
        ->unit_price->toBe(7.99)
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

    $pickup = $this->order->fresh()->pickUp([$line->id => 2], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

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
    $this->order->fresh()->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

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

describe('prix de vente', function () {
    it('fige sur la ligne le prix calculé au moment de l\'ajout', function () {
        $line = $this->order->addPart($this->part);

        $this->part->update(['last_cost' => 10]);
        $laterLine = CustomerOrder::factory()->create()->addPart($this->part->fresh());

        expect($line->fresh()->unit_price)->toBe(7.99)
            ->and($laterLine->unit_price)->toBe(25.0);
    });

    it('remet une pièce sans frais depuis la commande, et le total en tient compte', function () {
        $line = $this->order->addPart($this->part);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editNoCharge', true)
            ->assertSet('editUnitPrice', '0.00')
            ->call('saveLine')
            ->assertHasNoErrors();

        expect($line->fresh())
            ->is_no_charge->toBeTrue()
            ->unit_price->toBe(0.0)
            ->and($this->order->fresh())
            ->total->toBe(0.0)
            ->balance_due->toBe(0.0);
    });

    it('propose de nouveau le prix calculé quand « sans frais » est retiré', function () {
        $line = $this->order->addPart($this->part);
        $this->order->updatePartLine($line, 1, 0, true, null);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSet('editNoCharge', true)
            ->set('editNoCharge', false)
            ->assertSet('editUnitPrice', '7.99');
    });

    it('change le prix d\'une pièce déjà commandée, mais plus sa quantité', function () {
        $line = $this->order->addPart($this->part);
        $line->supplierOrderLine->order->send();

        $this->order->updatePartLine($line->fresh(), 1, 5, false, null);

        expect($line->fresh()->unit_price)->toBe(5.0)
            ->and(fn () => $this->order->updatePartLine($line->fresh(), 2, 5, false, null))->toThrow(DomainException::class);
    });

    it('ne change jamais le prix d\'une pièce remise au client', function () {
        $line = $this->order->addPart($this->part);
        $supplierLine = $line->supplierOrderLine;
        $supplierLine->order->send();
        $supplierLine->order->fresh()->receive([$supplierLine->id => ['quantity' => 1]]);
        $this->order->fresh()->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

        expect(fn () => $this->order->fresh()->updatePartLine($line->fresh(), 1, 0, true, null))->toThrow(DomainException::class);

        expect($line->fresh())
            ->unit_price->toBe(7.99)
            ->is_no_charge->toBeFalse();
    });
});
