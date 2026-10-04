<?php

use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\Accounting\Invoices\Create;
use App\Livewire\Accounting\Invoices\Form;
use App\Livewire\Accounting\Invoices\Index;
use App\Livewire\Orders\Show;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Commande de produits envoyée.
 *
 * @param  array<int, int>  $quantities
 * @return array{0: SupplierOrder, 1: array<int, SupplierOrderLine>}
 */
function orderToInvoice(?Supplier $supplier = null, array $quantities = [10], float $cost = 5.0): array
{
    $order = SupplierOrder::factory()->create(['supplier_id' => $supplier?->id ?? Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $lines = [];

    foreach ($quantities as $quantity) {
        $lines[] = SupplierOrderLine::factory()
            ->forProduct(Product::factory()->create(['supplier_id' => $order->supplier_id, 'cost' => $cost]))
            ->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => $cost]);
    }

    $order->send();

    return [$order->fresh(), $lines];
}

function receive(SupplierOrder $order, SupplierOrderLine $line, int $quantity): Reception
{
    return Reception::record($order->supplier, [$line->id => ['quantity' => $quantity]]);
}

it('cherche les réceptions par numéro de réception, bon de commande ou quote #', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    [$orderA, [$lineA]] = orderToInvoice($supplier, [3]);
    [$orderB, [$lineB]] = orderToInvoice($supplier, [3]);
    $orderB->update(['quote_number' => 'Q-9001']);
    $receptionA = receive($orderA, $lineA, 3);
    $receptionB = receive($orderB, $lineB, 3);

    Livewire::test(Index::class)
        ->assertSee($receptionA->number)->assertSee($receptionB->number)
        ->set('search', $orderA->number)
        ->assertSee($receptionA->number)->assertDontSee($receptionB->number)
        ->set('search', 'Q-9001')
        ->assertSee($receptionB->number)->assertDontSee($receptionA->number)
        ->set('search', $receptionA->number)
        ->assertSee($receptionA->number)->assertDontSee($receptionB->number);
});

it('affiche les réceptions d\'une commande partiellement reçue et cache les réceptions en cours ou facturées', function () {
    [$order, [$line]] = orderToInvoice(quantities: [10]);
    $partial = receive($order, $line, 4);
    $inProgress = Reception::start($order->supplier, [$line->id => ['quantity' => 2]]);
    $invoiced = receive($order, $line, 1);
    SupplierInvoice::record($invoiced, ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-03', 'invoice_total' => 5]);

    expect($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived);

    Livewire::test(Index::class)
        ->assertSee($partial->number)
        ->assertDontSee($inProgress->number)
        ->assertDontSee($invoiced->number)
        ->set('includeInvoiced', true)
        ->assertSee($invoiced->number)->assertSee('F-1');
});

it('facture une réception partielle: coûts réels, frais, écart et statut de la commande', function () {
    [$order, [$line]] = orderToInvoice(quantities: [10], cost: 5.0);
    $reception = receive($order, $line, 4);
    $receptionLine = $reception->lines->first();

    Livewire::test(Form::class, ['reception' => $reception])
        ->assertSet("unitCosts.{$receptionLine->id}", '5.00')
        ->set("unitCosts.{$receptionLine->id}", '5.75')
        ->set('invoiceNumber', 'F-100')
        ->set('invoiceDate', '2026-10-03')
        ->set('freightFee', '10')
        ->set('customsFee', '4.50')
        ->set('taxes', '3.25')
        ->set('invoiceTotal', '40.75')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = SupplierInvoice::first();
    expect($invoice)
        ->invoice_number->toBe('F-100')
        ->merchandise_total->toBe(23.0)
        ->freight_fee->toBe(10.0)->customs_fee->toBe(4.5)->taxes->toBe(3.25)
        ->computed_total->toBe(40.75)->invoice_total->toBe(40.75)->variance->toBe(0.0)
        ->and($invoice->hasVariance())->toBeFalse()
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first())->quantity->toBe(4)->unit_cost->toBe(5.75)
        ->and($receptionLine->fresh()->unit_cost)->toBe(5.75)
        ->and($line->fresh()->unit_cost)->toBe(5.75)
        ->and(InventoryUnit::pluck('cost')->unique()->all())->toBe([5.75])
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::PartiallyReceived);
});

it('passe la commande à facturée quand toutes ses réceptions sont facturées', function () {
    [$order, [$line]] = orderToInvoice(quantities: [10]);
    $first = receive($order, $line, 4);
    $second = receive($order, $line, 6);

    SupplierInvoice::record($first, ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-03', 'invoice_total' => 20]);
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Received);

    SupplierInvoice::record($second, ['invoice_number' => 'F-2', 'invoice_date' => '2026-10-04', 'invoice_total' => 30]);
    expect($order->fresh()->status)->toBe(SupplierOrderStatus::Invoiced);
});

it('compare le montant saisi au total calculé et enregistre l\'écart', function () {
    [$order, [$line]] = orderToInvoice(quantities: [2], cost: 10);
    $reception = receive($order, $line, 2);

    $component = Livewire::test(Form::class, ['reception' => $reception])
        ->set('freightFee', '5')
        ->set('taxes', '2')
        ->set('invoiceTotal', '30');

    expect($component->viewData('totals'))->toMatchArray(['merchandise_total' => 20.0, 'computed_total' => 27.0, 'variance' => 3.0]);

    $component->set('invoiceNumber', 'F-5')->call('save')->assertHasNoErrors();

    expect(SupplierInvoice::first())->variance->toBe(3.0)->and(SupplierInvoice::first()->hasVariance())->toBeTrue();
});

it('calcule l\'escompte de paiement rapide du fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 15]);
    [$order, [$line]] = orderToInvoice($supplier, [2], 50);
    $reception = receive($order, $line, 2);

    Livewire::test(Form::class, ['reception' => $reception])
        ->set('invoiceNumber', 'F-8')
        ->set('invoiceDate', '2026-10-01')
        ->set('invoiceTotal', '100')
        ->call('save');

    expect(SupplierInvoice::first())
        ->discount_percent->toBe(2.0)->discount_days->toBe(15)->discount_amount->toBe(2.0)
        ->and(SupplierInvoice::first()->discount_due_date->format('Y-m-d'))->toBe('2026-10-16');
});

it('sans escompte, aucune échéance d\'escompte', function () {
    [$order, [$line]] = orderToInvoice(quantities: [1]);
    SupplierInvoice::record(receive($order, $line, 1), ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-01', 'invoice_total' => 5]);

    expect(SupplierInvoice::first())->discount_due_date->toBeNull()->discount_amount->toBe(0.0);
});

it('valide la facture et refuse un numéro déjà saisi pour le fournisseur', function () {
    [$order, [$line]] = orderToInvoice(quantities: [2]);
    $first = receive($order, $line, 1);
    $second = receive($order, $line, 1);
    SupplierInvoice::record($first, ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-01', 'invoice_total' => 5]);
    $receptionLine = $second->lines->first();

    Livewire::test(Form::class, ['reception' => $second])
        ->call('save')
        ->assertHasErrors(['invoiceNumber', 'invoiceTotal']);

    Livewire::test(Form::class, ['reception' => $second])
        ->set('invoiceNumber', 'F-1')
        ->set('invoiceTotal', '5')
        ->call('save')
        ->assertHasErrors('invoiceNumber');

    Livewire::test(Form::class, ['reception' => $second])
        ->set('invoiceNumber', 'F-2')
        ->set('invoiceTotal', '5')
        ->set("unitCosts.{$receptionLine->id}", '-1')
        ->call('save')
        ->assertHasErrors("unitCosts.{$receptionLine->id}");

    expect(SupplierInvoice::count())->toBe(1);
});

it('refuse une deuxième facture, une réception en cours ou sans rien à facturer', function () {
    [$order, [$line]] = orderToInvoice(quantities: [3]);
    $reception = receive($order, $line, 2);
    $inProgress = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);
    $details = ['invoice_number' => 'F-1', 'invoice_date' => '2026-10-01', 'invoice_total' => 10];

    expect(fn () => SupplierInvoice::record($inProgress, $details))->toThrow(DomainException::class);

    SupplierInvoice::record($reception, $details);

    expect(fn () => SupplierInvoice::record($reception->fresh(), [...$details, 'invoice_number' => 'F-2']))->toThrow(DomainException::class);

    [$order2, [$line2]] = orderToInvoice(quantities: [2]);
    $reversed = receive($order2, $line2, 2);
    $reversed->lines->first()->reverse(2);

    expect(fn () => SupplierInvoice::record($reversed->fresh(), $details))->toThrow(DomainException::class);

    Livewire::test(Index::class)->assertDontSee($reversed->number);
});

it('affiche une facture enregistrée en lecture seule', function () {
    [$order, [$line]] = orderToInvoice(quantities: [2], cost: 10);
    $reception = receive($order, $line, 2);
    SupplierInvoice::record($reception, ['invoice_number' => 'F-READ', 'invoice_date' => '2026-10-01', 'invoice_total' => 25, 'taxes' => 3]);

    Livewire::test(Form::class, ['reception' => $reception])
        ->assertSee('F-READ')->assertSee('Facturée')->assertSee('Écart')
        ->assertDontSee('Enregistrer la facture');

    $this->get(route('accounting.invoices.reception', $reception))->assertOk();
});

it('refuse d\'ouvrir la facturation d\'une réception en cours', function () {
    [$order, [$line]] = orderToInvoice(quantities: [2]);
    $reception = Reception::start($order->supplier, [$line->id => ['quantity' => 1]]);

    $this->get(route('accounting.invoices.reception', $reception))->assertNotFound();
});

it('liste les factures dans la commande et bloque le renversement de la réception facturée', function () {
    [$order, [$line]] = orderToInvoice(quantities: [4]);
    $reception = receive($order, $line, 4);
    SupplierInvoice::record($reception, ['invoice_number' => 'F-ORD', 'invoice_date' => '2026-10-01', 'invoice_total' => 20]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertSee('F-ORD')
        ->assertDontSeeHtml('wire:click="openReverse');
});

it('enregistre l\'escompte et la règle dans l\'onglet comptabilité du fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('earlyPaymentDiscountPercent', '2.5')
        ->set('earlyPaymentDiscountDays', '15')
        ->call('saveAccounting')
        ->assertHasNoErrors();

    expect($supplier->fresh())->early_payment_discount_percent->toBe(2.5)->early_payment_discount_days->toBe(15);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier->fresh()])
        ->assertSet('earlyPaymentDiscountPercent', '2.5')
        ->assertSet('earlyPaymentDiscountDays', '15')
        ->set('earlyPaymentDiscountDays', '')
        ->call('saveAccounting')
        ->assertHasErrors('earlyPaymentDiscountDays')
        ->set('earlyPaymentDiscountPercent', '150')
        ->call('saveAccounting')
        ->assertHasErrors('earlyPaymentDiscountPercent');
});

it('calcule l\'échéance « mois suivant » au jour fixe du mois suivant, ramenée à la fin du mois', function () {
    $due = fn (string $date, int $day) => SupplierInvoice::discountDueDate(Carbon::parse($date), $day, true)->format('Y-m-d');

    expect($due('2026-10-03', 10))->toBe('2026-11-10')
        ->and($due('2026-12-20', 10))->toBe('2027-01-10')
        ->and($due('2026-01-31', 31))->toBe('2026-02-28')
        ->and($due('2026-10-31', 31))->toBe('2026-11-30');
});

it('facture avec un escompte « 2 % le 10 du mois suivant »', function () {
    $supplier = Supplier::factory()->create([
        'type' => SupplierType::Product, 'early_payment_discount_percent' => 2,
        'early_payment_discount_days' => 10, 'early_payment_next_month' => true,
    ]);
    [$order, [$line]] = orderToInvoice($supplier, [2], 50);
    $reception = receive($order, $line, 2);

    Livewire::test(Form::class, ['reception' => $reception])
        ->set('invoiceNumber', 'F-NM')
        ->set('invoiceDate', '2026-10-20')
        ->set('invoiceTotal', '100')
        ->assertSee('2026-11-10')
        ->call('save');

    expect(SupplierInvoice::first())
        ->discount_next_month->toBeTrue()->discount_days->toBe(10)->discount_amount->toBe(2.0)
        ->and(SupplierInvoice::first()->discount_due_date->format('Y-m-d'))->toBe('2026-11-10');
});

it('valide le jour du mois suivant et l\'enregistre avec le fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier])
        ->set('earlyPaymentDiscountPercent', '2')
        ->set('earlyPaymentNextMonth', true)
        ->set('earlyPaymentDiscountDays', '45')
        ->call('saveAccounting')
        ->assertHasErrors('earlyPaymentDiscountDays')
        ->set('earlyPaymentDiscountDays', '0')
        ->call('saveAccounting')
        ->assertHasErrors('earlyPaymentDiscountDays')
        ->set('earlyPaymentDiscountDays', '10')
        ->call('saveAccounting')
        ->assertHasNoErrors();

    expect($supplier->fresh())->early_payment_next_month->toBeTrue()->early_payment_discount_days->toBe(10);

    Livewire::test(SupplierShow::class, ['supplier' => $supplier->fresh()])->assertSet('earlyPaymentNextMonth', true);
});

function completedServiceOrder(?Supplier $supplier = null, float $cost = 100.0): SupplierOrder
{
    $order = SupplierOrder::factory()->service()->create(['supplier_id' => $supplier?->id ?? Supplier::factory()->create(['type' => SupplierType::Service])->id]);
    SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id, 'quantity' => 2, 'unit_cost' => $cost]);
    $order->send();
    $order->fresh()->complete();

    return $order->fresh();
}

it('trouve les commandes de services complétées dans la facturation, avec les réceptions', function () {
    $service = completedServiceOrder();
    $pending = SupplierOrder::factory()->service()->status(SupplierOrderStatus::Sent)->create();
    [$order, [$line]] = orderToInvoice(quantities: [3]);
    $reception = receive($order, $line, 3);

    Livewire::test(Index::class)
        ->assertSee($service->number)->assertSee('Services')
        ->assertSee($reception->number)->assertSee('Produits')
        ->assertDontSee($pending->number)
        ->set('search', $service->number)
        ->assertSee($service->number)->assertDontSee($reception->number)
        ->set('search', 'RC-')
        ->assertSee($reception->number)->assertDontSee($service->number);

    $service->update(['quote_number' => 'Q-SERV']);

    Livewire::test(Index::class)->set('search', 'Q-SERV')->assertSee($service->number)->assertDontSee($reception->number);
});

it('facture une commande de services: coûts réels, frais, écart et statut facturée', function () {
    $order = completedServiceOrder(cost: 100);
    $line = $order->lines->first();

    Livewire::test(Form::class, ['order' => $order])
        ->assertSee($order->number)
        ->assertSet("unitCosts.{$line->id}", '100.00')
        ->set("unitCosts.{$line->id}", '110')
        ->set('invoiceNumber', 'S-1')
        ->set('invoiceDate', '2026-10-05')
        ->set('freightFee', '5')
        ->set('taxes', '33')
        ->set('invoiceTotal', '258')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = SupplierInvoice::first();
    expect($invoice)
        ->supplier_order_id->toBe($order->id)->reception_id->toBeNull()
        ->merchandise_total->toBe(220.0)->computed_total->toBe(258.0)->variance->toBe(0.0)
        ->and($invoice->lines->first())->supplier_order_line_id->toBe($line->id)->reception_line_id->toBeNull()->quantity->toBe(2)->unit_cost->toBe(110.0)
        ->and($line->fresh()->unit_cost)->toBe(110.0)
        ->and($order->fresh()->status)->toBe(SupplierOrderStatus::Invoiced);

    Livewire::test(Form::class, ['order' => $order->fresh()])
        ->assertSee('Facturée')->assertSee('S-1')->assertDontSee('Enregistrer la facture');

    Livewire::test(Index::class)->assertDontSee($order->number)->set('includeInvoiced', true)->assertSee($order->number)->assertSee('S-1');
});

it('applique l\'escompte du fournisseur à une facture de services', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Service, 'early_payment_discount_percent' => 1, 'early_payment_discount_days' => 15]);
    $order = completedServiceOrder($supplier);

    SupplierInvoice::recordForOrder($order, ['invoice_number' => 'S-2', 'invoice_date' => '2026-10-01', 'invoice_total' => 200]);

    expect(SupplierInvoice::first())->discount_amount->toBe(2.0)
        ->and(SupplierInvoice::first()->discount_due_date->format('Y-m-d'))->toBe('2026-10-16');
});

it('refuse de facturer une commande de services non complétée, déjà facturée ou de produits', function () {
    $sent = SupplierOrder::factory()->service()->status(SupplierOrderStatus::Sent)->create();
    $order = completedServiceOrder();
    $details = ['invoice_number' => 'S-3', 'invoice_date' => '2026-10-01', 'invoice_total' => 10];

    expect(fn () => SupplierInvoice::recordForOrder($sent, $details))->toThrow(DomainException::class);

    SupplierInvoice::recordForOrder($order, $details);

    expect(fn () => SupplierInvoice::recordForOrder($order->fresh(), [...$details, 'invoice_number' => 'S-4']))->toThrow(DomainException::class);

    [$productOrder] = orderToInvoice();
    expect(fn () => SupplierInvoice::recordForOrder($productOrder, $details))->toThrow(DomainException::class);

    $this->get(route('accounting.invoices.order', $sent))->assertNotFound();
    $this->get(route('accounting.invoices.order', $productOrder))->assertNotFound();
});

it('liste la facture de services dans la commande', function () {
    $order = completedServiceOrder();
    SupplierInvoice::recordForOrder($order, ['invoice_number' => 'S-ORD', 'invoice_date' => '2026-10-01', 'invoice_total' => 200]);

    Livewire::test(Show::class, ['order' => $order->fresh()])
        ->assertSee('S-ORD')
        ->assertSee(route('accounting.invoices.order', $order), false);
});

it('crée une facture libre rattachée à un fournisseur, sans document', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Service, 'is_active' => true]);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceNumber', 'L-100')
        ->set('invoiceDate', '2026-10-05')
        ->set('description', 'Abonnement annuel')
        ->set('merchandiseTotal', '200')
        ->set('freightFee', '10')
        ->set('taxes', '31.50')
        ->set('invoiceTotal', '241.50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('accounting.invoices.show', SupplierInvoice::first()));

    $invoice = SupplierInvoice::first();
    expect($invoice)
        ->supplier_id->toBe($supplier->id)->reception_id->toBeNull()->supplier_order_id->toBeNull()
        ->description->toBe('Abonnement annuel')
        ->merchandise_total->toBe(200.0)->computed_total->toBe(241.5)->variance->toBe(0.0)
        ->and($invoice->isStandalone())->toBeTrue()
        ->and($invoice->lines)->toHaveCount(0)
        ->and(InventoryMovement::count())->toBe(0);

    $this->get(route('accounting.invoices.show', $invoice))->assertOk()->assertSee('L-100')->assertSee('Abonnement annuel')->assertSee('Libre');
});

it('compare le montant d\'une facture libre et applique l\'escompte du fournisseur', function () {
    $supplier = Supplier::factory()->create(['is_active' => true, 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 10, 'early_payment_next_month' => true]);

    $component = Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceDate', '2026-10-20')
        ->set('merchandiseTotal', '100')
        ->set('taxes', '15')
        ->set('invoiceTotal', '120');

    expect($component->viewData('totals'))->toMatchArray(['computed_total' => 115.0, 'variance' => 5.0, 'discount_amount' => 2.4]);

    $component->set('invoiceNumber', 'L-200')->call('save')->assertHasNoErrors();

    expect(SupplierInvoice::first())->variance->toBe(5.0)
        ->and(SupplierInvoice::first()->discount_due_date->format('Y-m-d'))->toBe('2026-11-10');
});

it('valide la facture libre: fournisseur actif, numéro unique et montants', function () {
    $supplier = Supplier::factory()->create(['is_active' => true]);
    $inactive = Supplier::factory()->create(['is_active' => false]);
    SupplierInvoice::recordStandalone($supplier, ['invoice_number' => 'L-1', 'invoice_date' => '2026-10-01', 'merchandise_total' => 10, 'invoice_total' => 10]);

    Livewire::test(Create::class)
        ->call('save')
        ->assertHasErrors(['supplierId', 'invoiceNumber', 'merchandiseTotal', 'invoiceTotal']);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $inactive->id)
        ->set('invoiceNumber', 'L-2')->set('merchandiseTotal', '5')->set('invoiceTotal', '5')
        ->call('save')
        ->assertHasErrors('supplierId');

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceNumber', 'L-1')->set('merchandiseTotal', '5')->set('invoiceTotal', '5')
        ->call('save')
        ->assertHasErrors('invoiceNumber');

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceNumber', 'L-3')->set('merchandiseTotal', '-5')->set('invoiceTotal', '5')
        ->call('save')
        ->assertHasErrors('merchandiseTotal');

    expect(SupplierInvoice::count())->toBe(1);
});

it('retrouve les factures libres dans la facturation et ouvre la bonne vue pour les autres', function () {
    $supplier = Supplier::factory()->create(['name' => 'Fournisseur Unique', 'is_active' => true]);
    $standalone = SupplierInvoice::recordStandalone($supplier, ['invoice_number' => 'L-77', 'invoice_date' => '2026-10-01', 'description' => 'Frais divers', 'merchandise_total' => 10, 'invoice_total' => 10]);
    [$order, [$line]] = orderToInvoice(quantities: [2]);
    $linked = SupplierInvoice::record(receive($order, $line, 2), ['invoice_number' => 'F-LINK', 'invoice_date' => '2026-10-01', 'invoice_total' => 10]);

    Livewire::test(Index::class)->assertDontSee('L-77')
        ->set('includeInvoiced', true)
        ->assertSee('L-77')->assertSee('Libre')
        ->set('search', 'Frais divers')->assertSee('L-77')
        ->set('search', 'Unique')->assertSee('L-77')
        ->set('search', 'L-77')->assertSee('L-77');

    $this->get(route('accounting.invoices.show', $standalone))->assertOk();
    $this->get(route('accounting.invoices.show', $linked))->assertRedirect(route('accounting.invoices.reception', $linked->reception_id));
});

it('limite la création de facture libre à la permission invoices.create', function () {
    $role = Role::create(['name' => 'lecture_factures', 'label' => 'Lecture', 'level' => 0, 'guard_name' => 'web']);
    $role->givePermissionTo(['invoices.view']);
    $this->actingAs(User::factory()->withRole('lecture_factures')->create());

    $this->get(route('accounting.invoices.create'))->assertForbidden();
    Livewire::test(Index::class)->assertDontSee('Nouvelle facture');
});
