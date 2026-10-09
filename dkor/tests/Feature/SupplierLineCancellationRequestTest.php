<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\SupplierOrderLineStatus;
use App\Livewire\CustomerOrders\Show as CustomerOrderShow;
use App\Livewire\SupplierLineCancellationRequest;
use App\Mail\SupplierOrderLineCancellationRequested;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Role;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
});

/**
 * Ligne client « Commandé » : produit sans stock mis en commande puis commande fournisseur envoyée.
 */
function orderedCustomerLine(CustomerOrder $order, int $quantity = 2): array
{
    $line = $order->addProduct(Product::factory()->create(), $quantity);
    $supplierOrder = SupplierOrder::sole();
    $supplierOrder->send();

    return [$line->fresh(), SupplierOrderLine::sole(), $supplierOrder->fresh()];
}

describe('depuis la commande client', function () {
    beforeEach(function () {
        $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    });

    it('propose la demande d\'annulation pour une ligne commandée', function () {
        [$line, $supplierLine] = orderedCustomerLine($this->order);

        Livewire::test(CustomerOrderShow::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSee('Demander l\'annulation au fournisseur')
            ->assertSeeHtml("open-supplier-line-cancellation', { lineId: {$supplierLine->id} }")
            ->assertDontSeeHtml('removeLine('.$line->id.')');
    });

    it('envoie la demande au fournisseur et met la ligne client en demande d\'annulation', function () {
        config(['supplier_orders.email_enabled' => true]);
        Mail::fake();
        [$line, $supplierLine, $supplierOrder] = orderedCustomerLine($this->order);
        $supplierOrder->supplier->update(['order_email' => 'commandes@fournisseur.test']);

        Livewire::test(SupplierLineCancellationRequest::class)
            ->call('open', $supplierLine->id)
            ->set('reason', 'Le client a changé d\'idée')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('supplier-line-cancellation-requested');

        expect($supplierLine->fresh())
            ->status->toBe(SupplierOrderLineStatus::CancellationRequested)
            ->cancellation_reason->toBe('Le client a changé d\'idée')
            ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::CancellationRequested);

        Mail::assertSent(SupplierOrderLineCancellationRequested::class, fn ($mail) => $mail->hasTo('commandes@fournisseur.test'));

        Livewire::test(CustomerOrderShow::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSee('en attente de sa réponse');
    });

    it('refuse une ligne fournisseur qui n\'appartient à aucun client', function () {
        $supplierOrder = SupplierOrder::factory()->create();
        $supplierLine = SupplierOrderLine::factory()->create(['supplier_order_id' => $supplierOrder->id]);

        Livewire::test(SupplierLineCancellationRequest::class)
            ->call('open', $supplierLine->id)
            ->assertForbidden();
    });
});

describe('réponse du fournisseur', function () {
    beforeEach(function () {
        [$this->line, $this->supplierLine, $this->supplierOrder] = orderedCustomerLine($this->order);
        $this->supplierLine->requestCancellation();
    });

    it('remet la ligne client à « Commandé » quand le fournisseur refuse', function () {
        $this->supplierOrder->rejectLineCancellation($this->supplierLine->fresh());

        expect($this->line->fresh()->status)->toBe(CustomerOrderLineStatus::Ordered);
    });

    it('annule la ligne client quand le fournisseur confirme', function () {
        $this->supplierOrder->confirmLineCancellation($this->supplierLine->fresh());

        expect($this->line->fresh()->status)->toBe(CustomerOrderLineStatus::Cancelled)
            ->and($this->order->fresh()->subtotal)->toBe(0.0);
    });

    it('garde la demande d\'annulation si une partie est reçue entre-temps', function () {
        $this->supplierOrder->receive([$this->supplierLine->id => ['quantity' => 1, 'unit_cost' => 5]]);

        expect($this->line->fresh())
            ->status->toBe(CustomerOrderLineStatus::CancellationRequested)
            ->quantity_reserved->toBe(1)
            ->quantity_on_order->toBe(1);
    });
});

describe('depuis la commande fournisseur', function () {
    it('permet la demande sur une ligne sans client avec la gestion des commandes fournisseurs', function () {
        $this->user->givePermissionTo(['supplier_orders.view', 'supplier_orders.edit']);
        $supplierOrder = SupplierOrder::factory()->create();
        $supplierLine = SupplierOrderLine::factory()->create(['supplier_order_id' => $supplierOrder->id, 'product_id' => Product::factory()->create(['supplier_id' => $supplierOrder->supplier_id])->id]);
        $supplierOrder->send();

        Livewire::test(SupplierLineCancellationRequest::class)
            ->call('open', $supplierLine->id)
            ->call('submit')
            ->assertHasNoErrors();

        expect($supplierLine->fresh()->status)->toBe(SupplierOrderLineStatus::CancellationRequested);
    });

    it('affiche l\'erreur quand la ligne ne peut plus faire l\'objet d\'une demande', function () {
        $this->user->givePermissionTo(['supplier_orders.view', 'supplier_orders.edit']);
        $supplierOrder = SupplierOrder::factory()->create();
        $supplierLine = SupplierOrderLine::factory()->create(['supplier_order_id' => $supplierOrder->id]);

        Livewire::test(SupplierLineCancellationRequest::class)
            ->call('open', $supplierLine->id)
            ->call('submit')
            ->assertHasErrors('reason')
            ->assertSet('showModal', true);
    });
});
