<?php

use App\Enums\SupplierOrderLineStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Events\SupplierOrderLineSubstituted;
use App\Livewire\Orders\Index;
use App\Livewire\Orders\Show;
use App\Mail\SupplierOrderLineCancellationRequested;
use App\Mail\SupplierOrderPlaced;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function sendOrder(SupplierOrder $order): void
{
    $order->markPending();
    $order->send();
}

function productOrderWithLine(int $quantity = 10, float $cost = 5.0): array
{
    $order = SupplierOrder::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => $cost]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create([
        'supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => $cost,
    ]);

    return [$order, $product, $line];
}

it('crée une commande de produits en brouillon avec un numéro', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(Index::class)
        ->set('newType', 'product')
        ->set('newSupplierId', (string) $supplier->id)
        ->call('create')
        ->assertHasNoErrors();

    $order = SupplierOrder::first();
    expect($order->status)->toBe(SupplierOrderStatus::Draft)
        ->and($order->number)->toBe('CF-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT))
        ->and($order->created_by)->toBe(auth()->id());
});

it('refuse un fournisseur d\'un autre type que celui de la commande', function () {
    $service = Supplier::factory()->create(['type' => SupplierType::Service]);

    Livewire::test(Index::class)
        ->set('newType', 'product')
        ->set('newSupplierId', (string) $service->id)
        ->call('create')
        ->assertHasErrors('newSupplierId');

    expect(SupplierOrder::count())->toBe(0);
});

it('n\'offre pas la commande pour un fournisseur d\'expédition', function () {
    $shipping = Supplier::factory()->create(['type' => SupplierType::Shipping]);

    Livewire::test(Index::class)
        ->set('newType', 'shipping')
        ->set('newSupplierId', (string) $shipping->id)
        ->call('create')
        ->assertHasErrors('newType');
});

it('ajoute une ligne produit avec le coût du produit par défaut', function () {
    $order = SupplierOrder::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 12.5]);

    Livewire::test(Show::class, ['order' => $order])
        ->set('productId', (string) $product->id)
        ->assertSet('unitCost', '12.50')
        ->set('quantity', '3')
        ->call('addLine')
        ->assertHasNoErrors();

    expect($order->lines()->first())
        ->product_id->toBe($product->id)
        ->quantity->toBe(3)
        ->unit_cost->toBe(12.5);
});

it('refuse un produit d\'un autre fournisseur ou non commandable', function () {
    $order = SupplierOrder::factory()->create();
    $other = Product::factory()->create();
    $blocked = Product::factory()->create(['supplier_id' => $order->supplier_id, 'is_non_orderable' => true]);

    foreach ([$other, $blocked] as $product) {
        Livewire::test(Show::class, ['order' => $order])
            ->set('productId', (string) $product->id)
            ->set('unitCost', '1')
            ->call('addLine')
            ->assertHasErrors('productId');
    }
});

it('ajoute une ligne libre à une commande de services', function () {
    $order = SupplierOrder::factory()->service()->create();

    Livewire::test(Show::class, ['order' => $order])
        ->set('description', 'Réparation du camion')
        ->set('quantity', '2')
        ->set('unitCost', '150')
        ->call('addLine')
        ->assertHasNoErrors();

    expect($order->lines()->first())->description->toBe('Réparation du camion')->product_id->toBeNull();
});

it('retire une ligne en brouillon seulement', function () {
    [$order, , $line] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])->call('removeLine', $line->id);
    expect($order->lines()->count())->toBe(0);

    [$sent, , $sentLine] = productOrderWithLine();
    $sent->update(['status' => SupplierOrderStatus::Sent]);

    Livewire::test(Show::class, ['order' => $sent])->call('removeLine', $sentLine->id)->assertForbidden();
});

it('refuse d\'envoyer une commande sans ligne', function () {
    $order = SupplierOrder::factory()->create();

    Livewire::test(Show::class, ['order' => $order])->call('send');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Draft);
});

it('met les quantités en commande à l\'envoi', function () {
    [$order, $product] = productOrderWithLine(10);

    Livewire::test(Show::class, ['order' => $order])->call('markPending')->call('send');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent)
        ->and($order->fresh()->sent_at)->not->toBeNull()
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(10);
});

it('réceptionne partiellement puis complètement avec le coût réel', function () {
    [$order, $product, $line] = productOrderWithLine(10, 5.0);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openReceive')
        ->set("receipts.{$line->id}.quantity", '4')
        ->set("receipts.{$line->id}.unit_cost", '5.50')
        ->call('receive')
        ->assertHasNoErrors();

    $stock = InventoryStock::where('product_id', $product->id)->first();
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived)
        ->and($stock->quantity_in_stock)->toBe(4)
        ->and($stock->quantity_on_order)->toBe(6)
        ->and(InventoryUnit::where('product_id', $product->id)->count())->toBe(4)
        ->and(InventoryUnit::first()->cost)->toBe(5.5);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openReceive')
        ->assertSet("receipts.{$line->id}.quantity", '6')
        ->call('receive');

    $stock->refresh();
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and($order->fresh()->received_at)->not->toBeNull()
        ->and($stock->quantity_in_stock)->toBe(10)
        ->and($stock->quantity_on_order)->toBe(0);
});

it('plafonne la réception à la quantité restante', function () {
    [$order, $product, $line] = productOrderWithLine(3);
    sendOrder($order);

    $order->fresh()->receive([$line->id => ['quantity' => 99, 'unit_cost' => 5]]);

    expect(InventoryUnit::where('product_id', $product->id)->count())->toBe(3)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Received);
});

it('refuse une réception sans quantité', function () {
    [$order, , $line] = productOrderWithLine(3);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openReceive')
        ->set("receipts.{$line->id}.quantity", '0')
        ->call('receive');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent)
        ->and(InventoryUnit::count())->toBe(0);
});

it('saisit la facture d\'une commande reçue', function () {
    [$order, , $line] = productOrderWithLine(2, 10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 2, 'unit_cost' => 10]]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openInvoice')
        ->assertSet('invoiceTotal', '20.00')
        ->set('invoiceNumber', 'F-1001')
        ->call('saveInvoice')
        ->assertHasNoErrors();

    expect($order->fresh())
        ->status->toBe(SupplierOrderStatus::Invoiced)
        ->invoice_number->toBe('F-1001')
        ->invoice_total->toBe(20.0);
});

it('refuse la facture tant que la commande n\'est pas reçue', function () {
    [$order] = productOrderWithLine();
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->set('invoiceNumber', 'F-1')
        ->set('invoiceDate', '2026-10-02')
        ->set('invoiceTotal', '10')
        ->call('saveInvoice');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('retire de l\'inventaire en commande le restant à l\'annulation', function () {
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('cancel');

    $stock = InventoryStock::where('product_id', $product->id)->first();
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Cancelled)
        ->and($stock->quantity_on_order)->toBe(0)
        ->and($stock->quantity_in_stock)->toBe(4);
});

it('annule un brouillon sans toucher à l\'inventaire', function () {
    [$order, $product] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])->call('cancel');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Cancelled)
        ->and(InventoryStock::where('product_id', $product->id)->exists())->toBeFalse();
});

it('complète puis facture une commande de services sans effet sur l\'inventaire', function () {
    $order = SupplierOrder::factory()->service()->create();
    SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id, 'quantity' => 2, 'unit_cost' => 100]);

    $component = Livewire::test(Show::class, ['order' => $order])->call('markPending')->call('send');
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);

    $component->call('complete');
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received);

    $component->call('openInvoice')->set('invoiceNumber', 'S-9')->call('saveInvoice');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Invoiced)
        ->and(InventoryStock::count())->toBe(0)
        ->and(InventoryUnit::count())->toBe(0);
});

it('filtre la liste par type et par statut', function () {
    $product = SupplierOrder::factory()->create();
    $service = SupplierOrder::factory()->service()->status(SupplierOrderStatus::Sent)->create();

    Livewire::test(Index::class)
        ->assertSee($product->number)->assertSee($service->number)
        ->set('typeFilter', 'service')
        ->assertDontSee($product->number)->assertSee($service->number)
        ->set('typeFilter', '')
        ->set('statusFilter', 'draft')
        ->assertSee($product->number)->assertDontSee($service->number);
});

it('modifie une ligne en brouillon sans toucher à l\'inventaire', function () {
    [$order, $product, $line] = productOrderWithLine(10, 5);

    Livewire::test(Show::class, ['order' => $order])
        ->call('startEditLine', $line->id)
        ->assertSet('editQuantity', '10')
        ->set('editQuantity', '4')
        ->set('editUnitCost', '6.25')
        ->call('saveLine')
        ->assertHasNoErrors()
        ->assertSet('editingLineId', null);

    expect($line->fresh())->quantity->toBe(4)->unit_cost->toBe(6.25)
        ->and(InventoryStock::where('product_id', $product->id)->exists())->toBeFalse();
});

it('ajuste « en commande » quand la quantité change après l\'envoi', function () {
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);

    $component = Livewire::test(Show::class, ['order' => $order->fresh()]);

    $component->call('startEditLine', $line->id)->set('editQuantity', '15')->call('saveLine');
    expect(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(15);

    $component->call('startEditLine', $line->id)->set('editQuantity', '6')->call('saveLine');
    expect(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(6)
        ->and($line->fresh()->quantity)->toBe(6);
});

it('refuse de descendre sous la quantité déjà reçue', function () {
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)
        ->set('editQuantity', '3')
        ->call('saveLine');

    expect($line->fresh()->quantity)->toBe(10)
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(6);
});

it('passe la commande à reçue quand la réduction égale ce qui est déjà reçu', function () {
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)
        ->set('editQuantity', '4')
        ->call('saveLine');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(0);
});

it('modifie une ligne de service après l\'envoi', function () {
    $order = SupplierOrder::factory()->service()->create();
    $line = SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id, 'quantity' => 2, 'unit_cost' => 100]);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)
        ->set('editQuantity', '3')
        ->set('editUnitCost', '90')
        ->call('saveLine');

    expect($line->fresh())->quantity->toBe(3)->unit_cost->toBe(90.0);
});

it('refuse la modification d\'une ligne une fois la commande reçue', function () {
    [$order, , $line] = productOrderWithLine(2);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 2, 'unit_cost' => 5]]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)
        ->set('editQuantity', '5')
        ->call('saveLine');

    expect($line->fresh()->quantity)->toBe(2);
});

it('ne remet pas la commande à partiellement reçue sans réception', function () {
    [$order, , $line] = productOrderWithLine(10);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)->set('editQuantity', '8')->call('saveLine');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('envoie une demande d\'annulation au fournisseur et met la ligne en demande', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order, $product, $line] = productOrderWithLine(10);
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    sendOrder($order);

    $notified = $line->fresh()->requestCancellation('Client a annulé');

    expect($notified)->toBeTrue()
        ->and($line->fresh())->status->toBe(SupplierOrderLineStatus::CancellationRequested)
        ->cancellation_reason->toBe('Client a annulé')
        ->cancellation_requested_at->not->toBeNull()
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(10);

    Mail::assertSent(SupplierOrderLineCancellationRequested::class, fn ($mail) => $mail->hasTo('commandes@fournisseur.test'));
});

it('utilise le courriel général si le fournisseur n\'a pas de courriel de commande, sinon avertit', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order, , $line] = productOrderWithLine(5);
    $order->supplier->update(['order_email' => null, 'email' => 'info@fournisseur.test']);
    sendOrder($order);

    expect($line->fresh()->requestCancellation())->toBeTrue();
    Mail::assertSent(SupplierOrderLineCancellationRequested::class, fn ($mail) => $mail->hasTo('info@fournisseur.test'));

    [$order2, , $line2] = productOrderWithLine(5);
    $order2->supplier->update(['order_email' => null, 'email' => null]);
    sendOrder($order2);

    expect($line2->fresh()->requestCancellation())->toBeFalse()
        ->and($line2->fresh()->status)->toBe(SupplierOrderLineStatus::CancellationRequested);
});

it('refuse la demande d\'annulation sur un brouillon ou une ligne déjà en demande', function () {
    [$order, , $line] = productOrderWithLine();

    expect(fn () => $line->fresh()->requestCancellation())->toThrow(DomainException::class);

    Mail::fake();
    sendOrder($order);
    $line->fresh()->requestCancellation();

    expect(fn () => $line->fresh()->requestCancellation())->toThrow(DomainException::class);
});

it('confirme l\'annulation: la ligne est annulée et « en commande » diminue', function () {
    Mail::fake();
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $line->fresh()->requestCancellation();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('confirmLineCancellation', $line->id);

    expect($line->fresh())->status->toBe(SupplierOrderLineStatus::Cancelled)->cancelled_at->not->toBeNull()
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(0)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Cancelled);
});

it('conserve ce qui est reçu quand l\'annulation d\'une ligne partiellement reçue est confirmée', function () {
    Mail::fake();
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);
    $line->fresh()->requestCancellation();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('confirmLineCancellation', $line->id);

    expect($line->fresh())->status->toBe(SupplierOrderLineStatus::Active)->quantity->toBe(4)
        ->and(InventoryStock::where('product_id', $product->id)->first())
        ->quantity_on_order->toBe(0)->quantity_in_stock->toBe(4)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Received);
});

it('remet la ligne active quand le fournisseur refuse', function () {
    Mail::fake();
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $line->fresh()->requestCancellation('raison');

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('rejectLineCancellation', $line->id);

    expect($line->fresh())->status->toBe(SupplierOrderLineStatus::Active)->cancellation_reason->toBeNull()
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(10);
});

it('garde la commande ouverte quand une seule de ses lignes est annulée et exclut cette ligne du total', function () {
    Mail::fake();
    [$order, , $cancelled] = productOrderWithLine(2, 10);
    $kept = SupplierOrderLine::factory()->forProduct(Product::factory()->create(['supplier_id' => $order->supplier_id]))
        ->create(['supplier_order_id' => $order->id, 'quantity' => 1, 'unit_cost' => 7]);
    sendOrder($order);
    $cancelled->fresh()->requestCancellation();
    $order->fresh()->confirmLineCancellation($cancelled->fresh());

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent)
        ->and($order->fresh()->load('lines')->total)->toBe(7.0);

    $order->fresh()->receive([$kept->id => ['quantity' => 1, 'unit_cost' => 7], $cancelled->id => ['quantity' => 2, 'unit_cost' => 10]]);

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and(InventoryUnit::count())->toBe(1);
});

it('gère la demande d\'annulation d\'une ligne de service', function () {
    Mail::fake();
    $order = SupplierOrder::factory()->service()->create();
    $line = SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id]);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openCancelRequest', $line->id)
        ->set('cancelReason', 'Plus nécessaire')
        ->call('requestLineCancellation')
        ->call('confirmLineCancellation', $line->id);

    expect($line->fresh()->status)->toBe(SupplierOrderLineStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Cancelled);
});

it('refuse de supprimer un brouillon qui contient une ligne', function () {
    [$order] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])->call('deleteOrder')->assertNoRedirect();

    expect(SupplierOrder::count())->toBe(1)
        ->and(SupplierOrderLine::count())->toBe(1);
});

it('supprime une commande en brouillon sans ligne', function () {
    $order = SupplierOrder::factory()->create();

    Livewire::test(Show::class, ['order' => $order])
        ->call('deleteOrder')
        ->assertRedirect(route('supplier-orders.index'));

    expect(SupplierOrder::count())->toBe(0);
});

it('refuse de supprimer une commande envoyée ou annulée', function (SupplierOrderStatus $status) {
    [$order] = productOrderWithLine();
    $order->update(['status' => $status]);

    Livewire::test(Show::class, ['order' => $order])->call('deleteOrder');

    expect(SupplierOrder::count())->toBe(1);
})->with([SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived, SupplierOrderStatus::Received, SupplierOrderStatus::Invoiced, SupplierOrderStatus::Cancelled]);

it('ajoute une ligne produit après l\'envoi et la met en commande', function () {
    [$order, $product] = productOrderWithLine(10);
    sendOrder($order);
    $other = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 8]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->set('productId', (string) $other->id)
        ->set('quantity', '5')
        ->call('addLine')
        ->assertHasNoErrors();

    expect($order->lines()->count())->toBe(2)
        ->and(InventoryStock::where('product_id', $other->id)->first()->quantity_on_order)->toBe(5)
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(10)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('ajoute une ligne à une commande partiellement reçue sans changer son statut', function () {
    [$order, , $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);
    $other = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 8]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->set('productId', (string) $other->id)
        ->call('addLine');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived)
        ->and($order->lines()->count())->toBe(2);
});

it('ajoute une ligne de service après l\'envoi', function () {
    $order = SupplierOrder::factory()->service()->create();
    SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id]);
    sendOrder($order);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->set('description', 'Livraison spéciale')
        ->set('unitCost', '75')
        ->call('addLine')
        ->assertHasNoErrors();

    expect($order->lines()->count())->toBe(2)
        ->and(InventoryStock::count())->toBe(0);
});

it('refuse l\'ajout d\'une ligne à une commande reçue ou annulée', function (SupplierOrderStatus $status) {
    [$order] = productOrderWithLine();
    $order->update(['status' => $status]);
    $other = Product::factory()->create(['supplier_id' => $order->supplier_id]);

    Livewire::test(Show::class, ['order' => $order])
        ->set('productId', (string) $other->id)
        ->set('unitCost', '5')
        ->call('addLine')
        ->assertForbidden();

    expect($order->lines()->count())->toBe(1);
})->with([SupplierOrderStatus::Received, SupplierOrderStatus::Invoiced, SupplierOrderStatus::Cancelled]);

it('substitue un produit: nouvelle ligne avec la quantité, ancienne à 0 et substituée', function () {
    Event::fake([SupplierOrderLineSubstituted::class]);
    [$order, $product, $line] = productOrderWithLine(10, 5);
    sendOrder($order);
    $new = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 9]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openSubstitute', $line->id)
        ->call('selectSubstitute', $new->id)
        ->assertSet('substituteProductId', $new->id)
        ->call('confirmSubstitute')
        ->assertHasNoErrors();

    $replacement = $order->lines()->where('product_id', $new->id)->first();

    expect($line->fresh())->status->toBe(SupplierOrderLineStatus::Substituted)->quantity->toBe(0)
        ->and($replacement)->quantity->toBe(10)->unit_cost->toBe(9.0)->status->toBe(SupplierOrderLineStatus::Active)
        ->substituted_from_line_id->toBe($line->id)
        ->and($line->fresh()->substitutedBy->id)->toBe($replacement->id)
        ->and(InventoryStock::where('product_id', $product->id)->first()->quantity_on_order)->toBe(0)
        ->and(InventoryStock::where('product_id', $new->id)->first()->quantity_on_order)->toBe(10)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Sent)
        ->and($order->fresh()->load('lines')->total)->toBe(90.0);

    Event::assertDispatched(SupplierOrderLineSubstituted::class, fn ($e) => $e->originalLine->is($line) && $e->replacementLine->is($replacement));
});

it('cherche les produits du fournisseur à substituer sans proposer le produit d\'origine', function () {
    [$order, $product, $line] = productOrderWithLine();
    sendOrder($order);
    $match = Product::factory()->create(['supplier_id' => $order->supplier_id, 'model' => 'Chaise Alpha']);
    $other = Product::factory()->create(['supplier_id' => $order->supplier_id, 'model' => 'Table Beta']);
    $foreign = Product::factory()->create(['model' => 'Chaise Étrangère']);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('openSubstitute', $line->id)
        ->set('substituteSearch', 'Chaise')
        ->assertViewHas('substituteResults', fn ($results) => $results->pluck('id')->all() === [$match->id])
        ->set('substituteSearch', '')
        ->assertViewHas('substituteResults', fn ($results) => $results->pluck('id')->sort()->values()->all() === collect([$match->id, $other->id])->sort()->values()->all())
        ->call('selectSubstitute', $foreign->id)
        ->assertNotFound();
});

it('ne substitue que ce qui reste à recevoir d\'une ligne partiellement reçue', function () {
    Event::fake([SupplierOrderLineSubstituted::class]);
    [$order, $product, $line] = productOrderWithLine(10);
    sendOrder($order);
    $order->fresh()->receive([$line->id => ['quantity' => 4, 'unit_cost' => 5]]);
    $new = Product::factory()->create(['supplier_id' => $order->supplier_id]);

    $replacement = $line->fresh()->substituteWith($new);

    expect($replacement->quantity)->toBe(6)
        ->and($line->fresh())->quantity->toBe(4)->status->toBe(SupplierOrderLineStatus::Active)
        ->and(InventoryStock::where('product_id', $product->id)->first())->quantity_on_order->toBe(0)->quantity_in_stock->toBe(4)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived);
});

it('réceptionne la ligne de remplacement et clôt la commande', function () {
    [$order, , $line] = productOrderWithLine(3);
    sendOrder($order);
    $new = Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => 2]);
    $replacement = $line->fresh()->substituteWith($new);

    $order->fresh()->receive([$line->id => ['quantity' => 3, 'unit_cost' => 5], $replacement->id => ['quantity' => 3, 'unit_cost' => 2]]);

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received)
        ->and(InventoryUnit::where('product_id', $new->id)->count())->toBe(3)
        ->and(InventoryUnit::where('product_id', $line->product_id)->count())->toBe(0);
});

it('refuse une substitution invalide', function () {
    [$order, , $line] = productOrderWithLine();
    $foreign = Product::factory()->create();
    $blocked = Product::factory()->create(['supplier_id' => $order->supplier_id, 'is_non_orderable' => true]);

    expect(fn () => $line->fresh()->substituteWith(Product::factory()->create(['supplier_id' => $order->supplier_id])))->toThrow(DomainException::class);

    sendOrder($order);

    expect(fn () => $line->fresh()->substituteWith($foreign))->toThrow(DomainException::class)
        ->and(fn () => $line->fresh()->substituteWith($blocked))->toThrow(DomainException::class)
        ->and(fn () => $line->fresh()->substituteWith($line->product))->toThrow(DomainException::class);
});

it('refuse de substituer une ligne en demande d\'annulation ou sur une commande de services', function () {
    Mail::fake();
    [$order, , $line] = productOrderWithLine();
    sendOrder($order);
    $line->fresh()->requestCancellation();

    expect(fn () => $line->fresh()->substituteWith(Product::factory()->create(['supplier_id' => $order->supplier_id])))->toThrow(DomainException::class);

    $service = SupplierOrder::factory()->service()->create();
    $serviceLine = SupplierOrderLine::factory()->create(['supplier_order_id' => $service->id]);
    sendOrder($service);

    expect(fn () => $serviceLine->fresh()->substituteWith(Product::factory()->create(['supplier_id' => $service->supplier_id])))->toThrow(DomainException::class);
});

it('refuse une deuxième commande en brouillon pour le même fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    SupplierOrder::factory()->create(['supplier_id' => $supplier->id]);

    Livewire::test(Index::class)
        ->set('newType', 'product')
        ->set('newSupplierId', (string) $supplier->id)
        ->call('create')
        ->assertHasErrors('newSupplierId');

    expect(fn () => SupplierOrder::factory()->create(['supplier_id' => $supplier->id]))->toThrow(DomainException::class)
        ->and(SupplierOrder::count())->toBe(1);
});

it('permet une nouvelle commande en brouillon une fois la précédente en attente', function () {
    [$order] = productOrderWithLine();
    $order->markPending();

    Livewire::test(Index::class)
        ->set('newType', 'product')
        ->set('newSupplierId', (string) $order->supplier_id)
        ->call('create')
        ->assertHasNoErrors();

    expect(SupplierOrder::where('supplier_id', $order->supplier_id)->count())->toBe(2)
        ->and(SupplierOrder::hasDraftFor($order->supplier_id))->toBeTrue();
});

it('met en attente un brouillon avec lignes, sans toucher à l\'inventaire', function () {
    [$order, $product] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])->call('markPending');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->fresh()->sent_at)->toBeNull()
        ->and(InventoryStock::where('product_id', $product->id)->exists())->toBeFalse();
});

it('refuse de mettre en attente un brouillon sans ligne', function () {
    $order = SupplierOrder::factory()->create();

    Livewire::test(Show::class, ['order' => $order])->call('markPending');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Draft);
});

it('refuse d\'envoyer un brouillon qui n\'est pas passé par l\'attente', function () {
    [$order] = productOrderWithLine();

    expect(fn () => $order->send())->toThrow(DomainException::class)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Draft);
});

it('garde les lignes modifiables et la commande annulable en attente', function () {
    [$order, $product, $line] = productOrderWithLine(10);
    $order->markPending();
    $other = Product::factory()->create(['supplier_id' => $order->supplier_id]);

    $component = Livewire::test(Show::class, ['order' => $order->fresh()])
        ->call('startEditLine', $line->id)->set('editQuantity', '6')->call('saveLine')
        ->set('productId', (string) $other->id)->call('addLine');

    expect($line->fresh()->quantity)->toBe(6)
        ->and($order->lines()->count())->toBe(2)
        ->and(InventoryStock::count())->toBe(0);

    $component->call('cancel');

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Cancelled);
});

it('supprime une commande en attente sans ligne mais pas avec une ligne', function () {
    [$order, , $line] = productOrderWithLine();
    $order->markPending();

    $component = Livewire::test(Show::class, ['order' => $order->fresh()])->call('deleteOrder')->assertNoRedirect();
    expect(SupplierOrder::count())->toBe(1);

    $component->call('removeLine', $line->id)
        ->call('deleteOrder')
        ->assertRedirect(route('supplier-orders.index'));

    expect(SupplierOrder::count())->toBe(0);
});

it('ne remet jamais une commande en attente ou envoyée en brouillon', function () {
    [$order] = productOrderWithLine();
    $order->markPending();

    expect(fn () => $order->fresh()->markPending())->toThrow(DomainException::class)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Pending);

    $order->fresh()->send();
    expect(fn () => $order->fresh()->markPending())->toThrow(DomainException::class)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

function shippingSupplier(array $attributes = []): Supplier
{
    return Supplier::factory()->create(['type' => SupplierType::Shipping, ...$attributes]);
}

it('refuse l\'envoi sous le montant prépayé et indique ce qui manque', function () {
    [$order] = productOrderWithLine(10, 5);
    $order->supplier->update(['prepaid_amount' => 100]);
    $order->markPending();

    expect($order->fresh()->missingForPrepaid())->toBe(50.0)
        ->and(fn () => $order->fresh()->send())->toThrow(DomainException::class);

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('send');
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Pending);
});

it('envoie prépayé quand le total atteint le montant prépayé', function () {
    [$order] = productOrderWithLine(20, 5);
    $order->supplier->update(['prepaid_amount' => 100]);
    $order->markPending();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('send');

    expect($order->fresh())->status->toBe(SupplierOrderStatus::Sent)->is_collect->toBeFalse();
});

it('permet d\'envoyer collect sous le montant prépayé avec un fournisseur d\'expédition', function () {
    [$order] = productOrderWithLine(10, 5);
    $order->supplier->update(['prepaid_amount' => 100]);
    $carrier = shippingSupplier();
    $order->markPending();

    $component = Livewire::test(Show::class, ['order' => $order->fresh()])
        ->set('isCollect', true)
        ->call('saveShipping')
        ->assertHasErrors('shippingSupplierId');

    $component->set('shippingSupplierId', (string) $carrier->id)
        ->call('saveShipping')
        ->assertHasNoErrors()
        ->call('send');

    expect($order->fresh())->status->toBe(SupplierOrderStatus::Sent)->is_collect->toBeTrue()->shipping_supplier_id->toBe($carrier->id);
});

it('refuse un fournisseur d\'expédition invalide', function () {
    [$order] = productOrderWithLine();
    $notCarrier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $inactive = shippingSupplier(['is_active' => false]);

    foreach ([$notCarrier, $inactive] as $supplier) {
        Livewire::test(Show::class, ['order' => $order])
            ->set('isCollect', true)
            ->set('shippingSupplierId', (string) $supplier->id)
            ->call('saveShipping')
            ->assertHasErrors('shippingSupplierId');
    }

    expect($order->fresh()->is_collect)->toBeFalse();
});

it('reprend le collect et le transporteur par défaut du fournisseur à la création', function () {
    $carrier = shippingSupplier();
    $supplier = Supplier::factory()->create([
        'type' => SupplierType::Product, 'collect' => true, 'default_shipping_supplier_id' => $carrier->id,
    ]);

    $order = SupplierOrder::factory()->create(['supplier_id' => $supplier->id]);

    expect($order->fresh())->is_collect->toBeTrue()->shipping_supplier_id->toBe($carrier->id);

    Livewire::test(Show::class, ['order' => $order])
        ->assertSet('isCollect', true)
        ->assertSet('shippingSupplierId', (string) $carrier->id);
});

it('impose le collect à un fournisseur collect et ignore le montant prépayé', function () {
    [$order] = productOrderWithLine(1, 1);
    $order->supplier->update(['collect' => true, 'prepaid_amount' => 0]);
    $order->markPending();

    expect(fn () => $order->fresh()->send())->toThrow(DomainException::class);

    $order->fresh()->setShipping(false, shippingSupplier()->id);
    expect($order->fresh()->is_collect)->toBeTrue();

    $order->fresh()->send();
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('ne touche pas au transport d\'une commande envoyée ni de services', function () {
    [$order] = productOrderWithLine(1, 1);
    sendOrder($order);

    expect(fn () => $order->fresh()->setShipping(true, shippingSupplier()->id))->toThrow(DomainException::class);

    $service = SupplierOrder::factory()->service()->create();
    SupplierOrderLine::factory()->create(['supplier_order_id' => $service->id]);
    $service->supplier->update(['prepaid_amount' => 100000]);

    sendOrder($service);
    expect($service->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('enregistre le quote # et le retrouve dans la recherche de la liste', function () {
    $order = SupplierOrder::factory()->create();
    $other = SupplierOrder::factory()->create();

    Livewire::test(Show::class, ['order' => $order])
        ->assertSet('quoteNumber', '')
        ->set('quoteNumber', 'Q-2026-0042')
        ->call('saveNotes')
        ->assertHasNoErrors();

    expect($order->fresh()->quote_number)->toBe('Q-2026-0042');

    Livewire::test(Index::class)
        ->set('search', 'Q-2026')
        ->assertSee($order->number)
        ->assertDontSee($other->number);

    Livewire::test(Show::class, ['order' => $order->fresh()])->set('quoteNumber', '')->call('saveNotes');
    expect($order->fresh()->quote_number)->toBeNull();
});

it('envoie la commande par courriel au courriel de commande du fournisseur', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order] = productOrderWithLine(2, 10);
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test', 'email' => 'info@fournisseur.test']);
    $order->update(['quote_number' => 'Q-77', 'notes' => 'Livrer à l\'arrière']);
    $order->markPending();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('send');

    Mail::assertSent(SupplierOrderPlaced::class, function ($mail) use ($order) {
        return $mail->hasTo('commandes@fournisseur.test')
            && $mail->order->is($order)
            && str_contains($mail->render(), $order->number)
            && str_contains($mail->render(), 'Q-77')
            && str_contains($mail->render(), '20.00')
            && str_contains($mail->render(), 'Livrer à l\'arrière');
    });
    Mail::assertSentCount(1);
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('mentionne le transport collect et le transporteur dans le courriel', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $carrier = shippingSupplier(['name' => 'Transport Rapide']);
    $order->setShipping(true, $carrier->id);
    $order->markPending();
    $order->fresh()->send();

    Mail::assertSent(SupplierOrderPlaced::class, fn ($mail) => str_contains($mail->render(), 'Transport Rapide'));
});

it('envoie la commande sans courriel de commande en utilisant le courriel général, sinon avertit', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => null, 'email' => 'info@fournisseur.test']);
    $order->markPending();
    expect($order->fresh()->send())->toBeTrue();
    Mail::assertSent(SupplierOrderPlaced::class, fn ($mail) => $mail->hasTo('info@fournisseur.test'));

    [$second] = productOrderWithLine();
    $second->supplier->update(['order_email' => null, 'email' => null]);
    $second->markPending();

    expect($second->fresh()->send())->toBeFalse()
        ->and($second->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('ne bloque pas l\'envoi quand le courriel échoue', function () {
    config(['supplier_orders.email_enabled' => true]);
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $order->markPending();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    expect($order->fresh()->send())->toBeFalse()
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Sent);
});

it('ne courriel pas un envoi refusé et renvoie le courriel d\'une commande envoyée', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order] = productOrderWithLine(1, 5);
    $order->supplier->update(['prepaid_amount' => 1000, 'order_email' => 'commandes@fournisseur.test']);
    $order->markPending();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('send');
    Mail::assertNothingSent();

    $order->supplier->update(['prepaid_amount' => 0]);
    $order->fresh()->send();

    Livewire::test(Show::class, ['order' => $order->fresh()])->call('resendEmail');
    Mail::assertSentCount(2);
});

it('refuse de renvoyer le courriel d\'un brouillon', function () {
    config(['supplier_orders.email_enabled' => true]);
    [$order] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])->call('resendEmail')->assertForbidden();
});

it('conserve la date d\'envoi et l\'affiche', function () {
    $this->travelTo('2026-10-05 14:30:00');
    [$order] = productOrderWithLine();
    sendOrder($order);

    expect($order->fresh()->sent_at->format('Y-m-d H:i'))->toBe('2026-10-05 14:30');

    Livewire::test(Show::class, ['order' => $order->fresh()])->assertSee('2026-10-05 14:30');
    Livewire::test(Index::class)->assertSee('2026-10-05');
});

it('conserve la date du dernier courriel réellement envoyé', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    $this->travelTo('2026-10-05 14:30:00');
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => null, 'email' => null]);
    sendOrder($order);

    expect($order->fresh()->last_emailed_at)->toBeNull();
    Livewire::test(Show::class, ['order' => $order->fresh()])->assertSee('Aucun courriel envoyé');

    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $this->travelTo('2026-10-06 09:00:00');
    Livewire::test(Show::class, ['order' => $order->fresh()])->call('resendEmail');

    expect($order->fresh())->last_emailed_at->format('Y-m-d H:i')->toBe('2026-10-06 09:00')
        ->sent_at->format('Y-m-d H:i')->toBe('2026-10-05 14:30');
});

it('ne met pas à jour la date du courriel quand l\'envoi échoue', function () {
    config(['supplier_orders.email_enabled' => true]);
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $order->markPending();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    $order->fresh()->send();

    expect($order->fresh()->last_emailed_at)->toBeNull();
});

function dropShipDestination(array $overrides = []): array
{
    return ['civic' => '123', 'apartment' => '4', 'street' => 'rue des Érables', 'city' => 'Laval', 'province' => 'QC', 'country' => 'CA', 'postal_code' => 'H7A1B2', ...$overrides];
}

it('enregistre une adresse de livraison drop ship', function () {
    [$order] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])
        ->set('isDropShip', true)
        ->set('dropShipName', 'Client Tremblay')
        ->set('dropShipAddress', dropShipDestination())
        ->call('saveDropShip')
        ->assertHasNoErrors();

    expect($order->fresh())
        ->is_drop_ship->toBeTrue()
        ->drop_ship_name->toBe('Client Tremblay')
        ->drop_ship_address_street->toBe('rue des Érables')
        ->drop_ship_address_postal_code->toBe('H7A1B2')
        ->and($order->fresh()->dropShipAddressLabel())->toContain('123 rue des Érables, app. 4')->toContain('Laval QC H7A1B2 CA');
});

it('exige le nom et l\'adresse pour un drop ship', function () {
    [$order] = productOrderWithLine();

    Livewire::test(Show::class, ['order' => $order])
        ->set('isDropShip', true)
        ->call('saveDropShip')
        ->assertHasErrors(['dropShipName', 'dropShipAddress.street', 'dropShipAddress.city', 'dropShipAddress.postal_code', 'dropShipAddress.civic']);

    expect($order->fresh()->is_drop_ship)->toBeFalse();
});

it('efface l\'adresse quand le drop ship est désactivé', function () {
    [$order] = productOrderWithLine();
    $order->setDropShip(true, ['name' => 'Client', ...dropShipDestination()]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertSet('isDropShip', true)
        ->set('isDropShip', false)
        ->call('saveDropShip');

    expect($order->fresh())->is_drop_ship->toBeFalse()->drop_ship_name->toBeNull()->drop_ship_address_street->toBeNull()
        ->and($order->fresh()->dropShipAddressLabel())->toBe('');
});

it('indique l\'adresse drop ship dans le courriel au fournisseur', function () {
    config(['supplier_orders.email_enabled' => true]);
    Mail::fake();
    [$order] = productOrderWithLine();
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $order->setDropShip(true, ['name' => 'Client Tremblay', ...dropShipDestination()]);
    sendOrder($order);

    Mail::assertSent(SupplierOrderPlaced::class, fn ($mail) => str_contains($mail->render(), 'Client Tremblay') && str_contains($mail->render(), 'rue des Érables'));
});

it('ne modifie pas le drop ship d\'une commande envoyée ni de services', function () {
    [$order] = productOrderWithLine();
    sendOrder($order);

    expect(fn () => $order->fresh()->setDropShip(true, ['name' => 'X', ...dropShipDestination()]))->toThrow(DomainException::class);

    $service = SupplierOrder::factory()->service()->create();

    expect(fn () => $service->setDropShip(true, ['name' => 'X', ...dropShipDestination()]))->toThrow(DomainException::class);
});

it('ferme et envoie la commande sans courriel quand le courriel est désactivé', function () {
    Mail::fake();
    [$order] = productOrderWithLine(3, 5);
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    $order->markPending();

    expect(config('supplier_orders.email_enabled'))->toBeFalse();

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertDontSee('Renvoyer le courriel')
        ->call('send');

    Mail::assertNothingSent();
    expect($order->fresh())->status->toBe(SupplierOrderStatus::Sent)->sent_at->not->toBeNull()->last_emailed_at->toBeNull();

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertDontSee('Renvoyer le courriel')
        ->assertDontSee('Aucun courriel envoyé')
        ->call('resendEmail')
        ->assertForbidden();
});

it('enregistre la demande d\'annulation sans courriel quand les courriels sont désactivés', function () {
    Mail::fake();
    [$order, , $line] = productOrderWithLine(5);
    $order->supplier->update(['order_email' => 'commandes@fournisseur.test']);
    sendOrder($order);

    expect(config('supplier_orders.email_enabled'))->toBeFalse()
        ->and($line->fresh()->requestCancellation('Client a annulé'))->toBeFalse()
        ->and($line->fresh())->status->toBe(SupplierOrderLineStatus::CancellationRequested)->cancellation_reason->toBe('Client a annulé');

    Mail::assertNothingSent();
});
