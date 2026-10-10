<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\DefectiveResolution;
use App\Enums\DefectiveStatus;
use App\Enums\InventoryMovementType;
use App\Enums\SupplierOrderStatus;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\DefectiveProduct;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\SupplierOrder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->cash = CustomerPaymentMethod::cash();
});

describe('pièce de remplacement', function () {
    it('commande la pièce sans frais chez le fournisseur et ouvre un dossier, le produit reste chez le client', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        $defective = $this->order->orderReplacementPart($line, 1, 'Pied avant gauche #P-221', 'Pied cassé à la livraison');

        $supplierLine = $defective->supplierOrderLine;

        expect($defective)
            ->resolution->toBe(DefectiveResolution::PartOrder)
            ->status->toBe(DefectiveStatus::Open)
            ->replacement_part->toBe('Pied avant gauche #P-221')
            ->reason->toBe('Pied cassé à la livraison')
            ->customer_order_line_id->toBe($line->id)
            ->and($supplierLine)
            ->product_id->toBeNull()
            ->quantity->toBe(1)
            ->unit_cost->toBe(0.0)
            ->description->toContain('P-221')
            ->description->toContain('commande client #'.$this->order->id)
            ->and($supplierLine->order)
            ->supplier_id->toBe($line->product->supplier_id)
            ->status->toBe(SupplierOrderStatus::Draft)
            ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
            ->and(InventoryMovement::where('type', InventoryMovementType::CustomerDefectiveReturn)->count())->toBe(0);
    });

    it('reçoit la pièce sans créer d\'unité d\'inventaire', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);
        $supplierLine = $this->order->orderReplacementPart($line, 1, 'Pied')->supplierOrderLine;
        $supplierOrder = $supplierLine->order;
        $supplierOrder->send();

        $unitsBefore = InventoryUnit::count();
        $supplierOrder->fresh()->receive([$supplierLine->id => ['quantity' => 1, 'unit_cost' => 0]]);

        expect(InventoryUnit::count())->toBe($unitsBefore)
            ->and($supplierLine->fresh()->quantity_received)->toBe(1)
            ->and($supplierOrder->fresh()->status)->toBe(SupplierOrderStatus::Received);
    });

    it('exige la pièce à commander', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        expect(fn () => $this->order->orderReplacementPart($line, 1, '  '))->toThrow(DomainException::class);
    });
});

describe('reprise du produit', function () {
    it('reprend le produit en inventaire défectueux et ajoute un remplacement au même prix', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);
        $line->product->inventoryStock()->update(['quantity_in_stock' => 1]);

        $defective = $this->order->returnDefective($line, 1, 'Moteur bruyant', replace: true);

        $replacement = $this->order->lines()->whereKeyNot($line->id)->sole();

        expect($defective)
            ->resolution->toBe(DefectiveResolution::Replacement)
            ->reason->toBe('Moteur bruyant')
            ->photo_path->toBeNull()
            ->customer_order_line_id->toBe($line->id)
            ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::Returned)
            ->and($replacement)
            ->product_id->toBe($line->product_id)
            ->unit_price->toBe(100.0)
            ->quantity_reserved->toBe(1)
            ->status->toBe(CustomerOrderLineStatus::InStock)
            ->note->toContain('#'.$defective->id)
            ->and($line->product->inventoryStock()->sole())
            ->quantity_defective_stock->toBe(1)
            ->quantity_in_stock->toBe(0)
            ->and(InventoryMovement::where('type', InventoryMovementType::CustomerDefectiveReturn)->sole()->quantity)->toBe(1)
            ->and($this->order->fresh()->balance_due)->toBe(0.0);
    });

    it('commande le remplacement quand il n\'y en a pas en stock', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        $this->order->returnDefective($line, 1, 'Moteur bruyant', replace: true);

        expect($this->order->lines()->whereKeyNot($line->id)->sole())
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->supplier_order_line_id->not->toBeNull();
    });

    it('reprend le produit et rembourse sans frais', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        $defective = $this->order->returnDefective($line, 1, 'Tissu déchiré', replace: false, refunds: [['method_id' => $this->cash->id, 'amount' => 114.98]]);

        expect($defective->resolution)->toBe(DefectiveResolution::Refund)
            ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
            ->and($this->order->fresh())->balance_due->toBe(0.0)->amount_paid->toBe(0.0)
            ->and($this->order->payments()->where('type', CustomerPaymentType::Refund)->sole()->amount)->toBe(-114.98)
            ->and($line->product->inventoryStock()->sole()->quantity_defective_stock)->toBe(1);
    });

    it('sépare la ligne quand une partie seulement est défectueuse', function () {
        $line = pickedUpLine($this->order, 2, 100, $this->cash);

        $defective = $this->order->returnDefective($line, 1, 'Égratignure', replace: false);

        expect($line->fresh())->quantity->toBe(1)->status->toBe(CustomerOrderLineStatus::PickedUp)
            ->and($defective->customerOrderLine)
            ->quantity->toBe(1)
            ->status->toBe(CustomerOrderLineStatus::Refunded);
    });

    it('exige la raison du défaut', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        expect(fn () => $this->order->returnDefective($line, 1, '', replace: true))->toThrow(DomainException::class);

        expect(DefectiveProduct::count())->toBe(0);
    });

    it('refuse un article qui n\'a pas été remis au client', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        expect(fn () => $this->order->returnDefective($line, 1, 'Défaut', replace: true))->toThrow(DomainException::class)
            ->and(fn () => $this->order->orderReplacementPart($line, 1, 'Pièce'))->toThrow(DomainException::class);
    });
});

describe('modal défectueux', function () {
    it('ouvre le modal depuis la ligne et commande une pièce', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSeeHtml('wire:click="openDefectiveModal('.$line->id.')"')
            ->call('openDefectiveModal', $line->id)
            ->assertSet('showDefectiveModal', true)
            ->assertSet('showLineModal', false)
            ->set('defectivePath', 'part')
            ->call('continueDefective')
            ->assertHasErrors('defectivePart')
            ->set('defectivePart', 'Coussin de siège')
            ->call('continueDefective')
            ->assertHasNoErrors()
            ->assertSet('showDefectiveModal', false)
            ->assertSee('Défectueux — dossier #');

        expect(DefectiveProduct::sole()->replacement_part)->toBe('Coussin de siège')
            ->and(SupplierOrder::sole()->lines()->sole()->description)->toContain('Coussin de siège');
    });

    it('reprend le produit et le remplace', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openDefectiveModal', $line->id)
            ->set('defectivePath', 'return')
            ->assertSee('Bientôt disponible.')
            ->call('continueDefective')
            ->assertHasErrors('defectiveReason')
            ->set('defectiveReason', 'Ne s\'allume pas')
            ->set('defectiveOutcome', 'replace')
            ->call('continueDefective')
            ->assertHasNoErrors()
            ->assertSet('showDefectiveModal', false);

        expect(DefectiveProduct::sole()->resolution)->toBe(DefectiveResolution::Replacement)
            ->and($this->order->lines()->count())->toBe(2);
    });

    it('reprend le produit et rembourse en deux étapes', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openDefectiveModal', $line->id)
            ->set('defectivePath', 'return')
            ->set('defectiveReason', 'Ne s\'allume pas')
            ->set('defectiveOutcome', 'refund')
            ->call('continueDefective')
            ->assertSet('defectiveStep', 'refund')
            ->assertSet('defectiveRefundable', 114.98)
            ->call('confirmDefectiveRefund')
            ->assertHasNoErrors()
            ->assertSet('showDefectiveModal', false);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
            ->and(DefectiveProduct::sole()->resolution)->toBe(DefectiveResolution::Refund);
    });
});
