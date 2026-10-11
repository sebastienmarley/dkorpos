<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\InventoryMovementType;
use App\Enums\SupplierOrderStatus;
use App\Livewire\Accounting\Invoices\Form as InvoiceForm;
use App\Livewire\Receptions\Show as ReceptionShow;
use App\Mail\ReceptionDiscrepancyReported;
use App\Models\CustomerOrder;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config(['supplier_orders.email_enabled' => true]);
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Commande de produits envoyée au fournisseur (courriel de commande connu), avec une ligne à 5 $.
 *
 * @return array{0: SupplierOrder, 1: Product, 2: SupplierOrderLine}
 */
function shippedOrder(int $quantity): array
{
    $supplier = Supplier::factory()->create(['type' => 'product', 'order_email' => 'commandes@fournisseur.test']);
    $order = SupplierOrder::factory()->create(['supplier_id' => $supplier->id]);
    $product = Product::factory()->create(['supplier_id' => $supplier->id, 'model' => 'TABLE-ACAJOU', 'cost' => Money::parse(5)]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse(5)]);
    $order->send();
    Mail::fake();

    return [$order->fresh(), $product, $line->fresh()];
}

describe('articles manquants', function () {
    it('ferme les manquants sur la commande, les retire d\'« en commande » et avise le fournisseur', function () {
        [$order, $product, $line] = shippedOrder(5);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 5]]);
        $receptionLine = $reception->lines()->sole();

        Livewire::test(ReceptionShow::class, ['reception' => $reception])
            ->set("quantities.{$receptionLine->id}", '3')
            ->set("short.{$receptionLine->id}", '2')
            ->call('complete')
            ->assertHasNoErrors()
            ->assertSee('2 article(s) manquant(s)');

        expect($line->fresh())
            ->quantity_received->toBe(3)
            ->quantity_short->toBe(2)
            ->quantity_outstanding->toBe(0)
            ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
            ->and(InventoryStock::where('product_id', $product->id)->sole())
            ->quantity_in_stock->toBe(3)
            ->quantity_on_order->toBe(0)
            ->and(InventoryMovement::where('type', InventoryMovementType::ShortShipped)->sole()->quantity)->toBe(2);

        Mail::assertSent(ReceptionDiscrepancyReported::class, fn (ReceptionDiscrepancyReported $mail): bool => $mail->hasTo('commandes@fournisseur.test')
            && $mail->reception->shortLines()->sole()->quantity_short === 2
            && $mail->reception->overshipLines()->isEmpty());
    });

    it('accepte une ligne entièrement manquante', function () {
        [$order, $product, $line] = shippedOrder(2);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'short' => 2]]);

        $reception->complete();

        expect($line->fresh())->quantity_received->toBe(0)->quantity_short->toBe(2)
            ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
            ->and(InventoryUnit::count())->toBe(0);
    });

    it('recommande au fournisseur les articles manquants qu\'un client attend', function () {
        [$order, $product, $line] = shippedOrder(1);
        $customerOrder = CustomerOrder::factory()->create();
        $customerLine = $customerOrder->addProduct($product, 2);
        $supplierLine = $customerLine->supplierOrderLine;
        $supplierLine->order->send();

        $reception = Reception::start($order->supplier, [$supplierLine->id => ['quantity' => 0, 'short' => 2]]);
        $reception->complete();

        $customerLine = $customerLine->fresh();
        $reorder = $customerLine->supplierOrderLine;

        expect($customerLine)
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->quantity_on_order->toBe(2)
            ->and($reorder->id)->not->toBe($supplierLine->id)
            ->and($reorder->quantity)->toBe(2)
            ->and($reorder->order->status)->toBe(SupplierOrderStatus::Draft);
    });

    it('refuse plus de reçus et de manquants que ce qui reste à recevoir, ou aucun des deux', function (int $received, int $short) {
        [$order, $product, $line] = shippedOrder(5);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 5]]);

        expect(fn () => $reception->setLineQuantity($reception->lines()->sole(), $received, 0, $short))->toThrow(DomainException::class);

        expect($reception->lines()->sole())->quantity->toBe(5)->quantity_short->toBe(0);
    })->with([
        'plus que le restant' => [4, 2],
        'ni reçu ni manquant' => [0, 0],
    ]);
});

describe('articles reçus en trop', function () {
    it('ajoute un article reçu en trop à coût nul, qui entre en inventaire', function () {
        [$order, $product, $line] = shippedOrder(1);
        $extra = Product::factory()->create(['supplier_id' => $order->supplier_id, 'model' => 'TABLE-ERABLE', 'cost' => Money::parse(80)]);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);

        Livewire::test(ReceptionShow::class, ['reception' => $reception])
            ->set('overshipSearch', 'erable')
            ->assertSee('TABLE-ERABLE')
            ->set('overshipQuantity', '2')
            ->call('addOvership', $extra->id)
            ->call('complete')
            ->assertHasNoErrors();

        $overshipLine = SupplierOrderLine::where('product_id', $extra->id)->sole();

        expect($overshipLine)
            ->is_overship->toBeTrue()
            ->unit_cost->toBeMoney(0)
            ->quantity_received->toBe(2)
            ->and(InventoryStock::where('product_id', $extra->id)->sole()->quantity_in_stock)->toBe(2)
            ->and(InventoryUnit::where('product_id', $extra->id)->pluck('cost')->map->cents()->all())->toBe([0, 0]);

        Mail::assertSent(ReceptionDiscrepancyReported::class, fn (ReceptionDiscrepancyReported $mail): bool => $mail->reception->overshipLines()->sole()->quantity === 2);
    });

    it('refuse un article en trop d\'un autre fournisseur', function () {
        [$order, $product, $line] = shippedOrder(1);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);

        expect(fn () => $reception->addOvership(Product::factory()->create(), 1))->toThrow(DomainException::class);

        expect(SupplierOrderLine::count())->toBe(1);
    });

    it('retire la ligne ajoutée à la commande quand l\'article en trop est retiré de la réception', function () {
        [$order, $product, $line] = shippedOrder(1);
        $extra = Product::factory()->create(['supplier_id' => $order->supplier_id]);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);
        $overshipLine = $reception->addOvership($extra, 3);

        $reception->removeLine($overshipLine);

        expect(SupplierOrderLine::where('product_id', $extra->id)->exists())->toBeFalse()
            ->and(InventoryStock::where('product_id', $extra->id)->value('quantity_on_order') ?? 0)->toBe(0);
    });

    it('retire la ligne ajoutée à la commande quand la réception est abandonnée', function () {
        [$order, $product, $line] = shippedOrder(1);
        $extra = Product::factory()->create(['supplier_id' => $order->supplier_id]);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);
        $reception->addOvership($extra, 3);

        $reception->discard();

        expect(SupplierOrderLine::where('product_id', $extra->id)->exists())->toBeFalse()
            ->and(InventoryStock::where('product_id', $extra->id)->value('quantity_on_order') ?? 0)->toBe(0);
    });
});

it('déclare une erreur de couleur : la couleur commandée manque, l\'autre est reçue en trop', function () {
    [$order, $product, $line] = shippedOrder(1);
    $wrongColor = Product::factory()->create(['supplier_id' => $order->supplier_id, 'model' => 'TABLE-ERABLE']);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'short' => 1]]);
    $reception->addOvership($wrongColor, 1);

    $reception->complete();

    expect(InventoryStock::where('product_id', $product->id)->sole()->quantity_in_stock)->toBe(0)
        ->and(InventoryStock::where('product_id', $wrongColor->id)->sole()->quantity_in_stock)->toBe(1);

    Mail::assertSent(ReceptionDiscrepancyReported::class, fn (ReceptionDiscrepancyReported $mail): bool => $mail->reception->shortLines()->count() === 1
        && $mail->reception->overshipLines()->count() === 1);
});

it('n\'avise pas le fournisseur d\'une réception sans écart', function () {
    [$order, $product, $line] = shippedOrder(2);

    Reception::start($order->supplier, [$line->id => ['quantity' => 2]])->complete();

    Mail::assertNothingSent();
});

describe('facturation', function () {
    it('facture les manquants et demande un crédit qui balance la facture', function () {
        [$order, $product, $line] = shippedOrder(5);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 3, 'short' => 2]]);
        $reception->complete();

        $invoice = SupplierInvoice::record($reception->fresh(), ['invoice_number' => 'F-66', 'invoice_date' => '2026-10-11', 'invoice_total' => '25.00']);

        expect($invoice->lines->sole())->quantity->toBe(5)->quantity_short->toBe(2)
            ->and($invoice)
            ->merchandise_total->toBeMoney(2500)
            ->variance->toBeMoney(0)
            ->credit_requested->toBeMoney(1000)
            ->and($invoice->netTotal())->toBeMoney(1500)
            ->and($invoice->isCreditPending())->toBeTrue()
            ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Invoiced);
    });

    it('facture une réception dont tout est manquant', function () {
        [$order, $product, $line] = shippedOrder(2);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 0, 'short' => 2]]);
        $reception->complete();

        $invoice = SupplierInvoice::record($reception->fresh(), ['invoice_number' => 'F-67', 'invoice_date' => '2026-10-11', 'invoice_total' => '10.00']);

        expect($invoice)->credit_requested->toBeMoney(1000)->and($invoice->netTotal())->toBeMoney(0)
            ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Invoiced);
    });

    it('affiche la demande de crédit pendant la saisie de la facture', function () {
        [$order, $product, $line] = shippedOrder(5);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 3, 'short' => 2]]);
        $reception->complete();

        $component = Livewire::test(InvoiceForm::class, ['reception' => $reception->fresh()])
            ->set('invoiceTotal', '25.00')
            ->assertSee('Demande de crédit (manquants)')
            ->assertSee('dont 2 manquant(s)');

        expect($component->viewData('totals')['credit_requested'])->toBeMoney(1000);
    });

    it('inscrit la note de crédit du fournisseur', function () {
        [$order, $product, $line] = shippedOrder(5);
        $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 3, 'short' => 2]]);
        $reception->complete();
        $invoice = SupplierInvoice::record($reception->fresh(), ['invoice_number' => 'F-68', 'invoice_date' => '2026-10-11', 'invoice_total' => '25.00']);

        Livewire::test(InvoiceForm::class, ['reception' => $reception->fresh()])
            ->set('creditNoteNumber', 'NC-123')
            ->set('creditReceivedAt', '2026-10-20')
            ->call('recordCreditNote')
            ->assertHasNoErrors();

        expect($invoice->fresh())
            ->credit_note_number->toBe('NC-123')
            ->credit_received_at->toDateString()->toBe('2026-10-20')
            ->and($invoice->fresh()->isCreditPending())->toBeFalse()
            ->and(fn () => $invoice->fresh()->recordCreditNote('NC-124', now()))->toThrow(DomainException::class);
    });
});
