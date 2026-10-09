<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\InventoryMovementType;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Role;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->cash = CustomerPaymentMethod::factory()->create(['name' => 'Comptant']);
    $this->visa = CustomerPaymentMethod::factory()->create(['name' => 'Visa']);
});

it('calcule la TPS et la TVQ sur les lignes facturables', function () {
    stockedLine($this->order, 1, 1, 100);

    expect($this->order->fresh())
        ->subtotal->toBe(100.0)
        ->gst->toBe(5.0)
        ->qst->toBe(9.98)
        ->total->toBe(114.98)
        ->balance_due->toBe(114.98);
});

it('exige 100 % des articles ramassés et 30 % de dépôt sur le reste, taxes incluses', function () {
    $pickedLine = stockedLine($this->order, 1, 1, 100);
    stockedLine($this->order, 0, 1, 200);

    // 100 $ remis : 114,98 $; 200 $ restant : 229,95 $ × 30 % = 68,985 $
    expect($this->order->amountRequiredFor([$pickedLine->id => 1]))->toBe(183.97);
});

it('ramasse une ligne complète, sort les unités de l\'inventaire et encaisse le paiement', function () {
    $line = stockedLine($this->order, 2, 2, 100);

    $this->order->pickUp([$line->id => 2], [['method_id' => $this->cash->id, 'amount' => 229.95]]);

    $order = $this->order->fresh();

    expect($line->fresh())
        ->status->toBe(CustomerOrderLineStatus::PickedUp)
        ->quantity->toBe(2)
        ->quantity_reserved->toBe(0)
        ->delivered_at->not->toBeNull()
        ->customer_order_pickup_id->toBe($order->pickups()->sole()->id)
        ->and($order)
        ->status->toBe(CustomerOrderStatus::PickedUp)
        ->amount_paid->toBe(229.95)
        ->balance_due->toBe(0.0)
        ->and($line->product->inventoryStock()->sole())
        ->quantity_in_stock->toBe(0)
        ->quantity_reserved->toBe(0)
        ->and(InventoryUnit::whereNull('delivered_at')->count())->toBe(0)
        ->and(InventoryMovement::where('type', InventoryMovementType::CustomerPickup)->sole()->quantity)->toBe(2);
});

it('sépare la ligne ramassée en partie : la partie remise devient une ligne « Ramassé »', function () {
    $line = stockedLine($this->order, 2, 3, 100);

    $this->order->pickUp([$line->id => 2], [['method_id' => $this->cash->id, 'amount' => 264.44]]);

    expect($line->fresh())
        ->quantity_reserved->toBe(0)
        ->quantity_on_order->toBe(1)
        ->quantity->toBe(1)
        ->status->toBe(CustomerOrderLineStatus::OnOrder);

    expect($this->order->lines()->where('status', CustomerOrderLineStatus::PickedUp)->sole())
        ->quantity->toBe(2)
        ->unit_price->toBe(100.0)
        ->product_id->toBe($line->product_id)
        ->and($this->order->fresh()->status)->toBe(CustomerOrderStatus::New);
});

it('accepte un paiement réparti sur plusieurs modes', function () {
    $line = stockedLine($this->order, 1, 1, 100);

    $this->order->pickUp([$line->id => 1], [
        ['method_id' => $this->cash->id, 'amount' => 50],
        ['method_id' => $this->visa->id, 'amount' => 64.98],
    ]);

    expect($this->order->payments()->pluck('amount', 'customer_payment_method_id')->all())
        ->toBe([$this->cash->id => 50.0, $this->visa->id => 64.98]);
});

it('refuse un paiement insuffisant sans rien changer', function () {
    $line = stockedLine($this->order, 1, 1, 100);

    expect(fn () => $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => 100]]))
        ->toThrow(DomainException::class);

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::InStock)
        ->and($this->order->payments()->count())->toBe(0)
        ->and($this->order->pickups()->count())->toBe(0)
        ->and($line->product->inventoryStock()->sole()->quantity_reserved)->toBe(1);
});

it('refuse un paiement qui dépasse le solde', function () {
    $line = stockedLine($this->order, 1, 1, 100);

    expect(fn () => $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => 200]]))
        ->toThrow(DomainException::class);
});

it('refuse de ramasser plus que le stock réservé', function () {
    $line = stockedLine($this->order, 1, 2, 100);

    expect(fn () => $this->order->pickUp([$line->id => 2], [['method_id' => $this->cash->id, 'amount' => 229.96]]))
        ->toThrow(DomainException::class);
});

it('tient compte du dépôt déjà payé au ramassage suivant', function () {
    $first = stockedLine($this->order, 1, 1, 100);
    $second = stockedLine($this->order, 1, 1, 100);

    $this->order->pickUp([$first->id => 1], [['method_id' => $this->cash->id, 'amount' => 149.47]]);

    expect($this->order->fresh()->amountRequiredFor([$second->id => 1]))->toBe(80.48);
});

it('bloque le renversement d\'une réception dont la marchandise a été ramassée', function () {
    $line = $this->order->addProduct(Product::factory()->create(), 1);
    $supplierOrder = SupplierOrder::sole();
    $supplierOrder->send();
    $reception = $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 1, 'unit_cost' => 5]]);

    $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => $this->order->fresh()->balance_due]]);

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
        ->and(fn () => $supplierOrder->fresh()->reverseReceipt($reception->lines()->sole(), 1))->toThrow(DomainException::class);
});

it('encaisse un paiement sans ramassage quand aucun article n\'est choisi', function () {
    $line = stockedLine($this->order, 1, 1, 100);

    expect($this->order->amountRequiredFor([]))->toBe(34.49);

    $pickup = $this->order->pickUp([], [['method_id' => $this->cash->id, 'amount' => 50]]);

    expect($pickup)->toBeNull()
        ->and($this->order->pickups()->count())->toBe(0)
        ->and($this->order->payments()->sole())->amount->toBe(50.0)->customer_order_pickup_id->toBeNull()
        ->and($this->order->fresh()->balance_due)->toBe(64.98)
        ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::InStock);
});

it('refuse un paiement sans ramassage inférieur au dépôt exigé', function () {
    stockedLine($this->order, 0, 1, 100);

    expect(fn () => $this->order->pickUp([], [['method_id' => $this->cash->id, 'amount' => 10]]))
        ->toThrow(DomainException::class);
});

it('refuse un passage sans article ni paiement', function () {
    stockedLine($this->order, 1, 1, 100);

    expect(fn () => $this->order->pickUp([], []))->toThrow(DomainException::class);
});

describe('modal de ramassage', function () {
    it('passe au paiement sans article quand tout est à 0', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->set("pickupQuantities.{$line->id}", 0)
            ->call('continueToPayment')
            ->assertSet('pickupStep', 'payment')
            ->assertSet('pickupRequired', 34.49)
            ->call('confirmPickup')
            ->assertHasNoErrors();

        expect($this->order->pickups()->count())->toBe(0)
            ->and($this->order->fresh()->amount_paid)->toBe(34.49)
            ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::InStock);
    });

    it('affiche le bouton pour payer même sans stock réservé', function () {
        stockedLine($this->order, 0, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->assertSet('pickupQuantities', [])
            ->call('continueToPayment')
            ->assertSet('pickupStep', 'payment')
            ->assertSet('pickupRequired', 34.49);
    });

    it('propose les lignes avec du stock réservé, calcule le montant puis enregistre le ramassage', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        stockedLine($this->order, 0, 1, 50);

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertSee('Ramasser')
            ->call('openPickupModal')
            ->assertSet('pickupQuantities', [$line->id => 1])
            ->call('continueToPayment')
            ->assertSet('pickupStep', 'payment')
            ->assertSet('pickupRequired', 132.23)
            ->call('confirmPickup')
            ->assertHasNoErrors()
            ->assertSet('showPickupModal', false);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
            ->and($this->order->fresh()->amount_paid)->toBe(132.23);
    });

    it('refuse une quantité supérieure au disponible', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->set("pickupQuantities.{$line->id}", 2)
            ->call('continueToPayment')
            ->assertHasErrors("pickupQuantities.{$line->id}")
            ->assertSet('pickupStep', 'items');
    });

    it('affiche l\'erreur quand le paiement est insuffisant', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->set('pickupPayments.0.amount', '10')
            ->call('confirmPickup')
            ->assertHasErrors('pickupPayments')
            ->assertSet('showPickupModal', true);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::InStock);
    });

    it('n\'affiche pas le bouton sans article à ramasser ni solde', function () {
        Livewire::test(Show::class, ['order' => $this->order])->assertDontSeeHtml('wire:click="openPickupModal"');
    });
});

describe('crédit client comme mode de paiement', function () {
    beforeEach(function () {
        $this->order->customer->forceFill(['credit_balance' => 50])->save();
    });

    it('paie en partie avec le crédit au compte et le déduit du client', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => 64.98]], creditUsed: 50);

        $order = $this->order->fresh();

        expect($order->balance_due)->toBe(0.0)
            ->and($order->customer->credit_balance)->toBe(0.0)
            ->and($order->payments()->where('type', CustomerPaymentType::CreditUse)->sole())
            ->amount->toBe(50.0)
            ->customer_payment_method_id->toBeNull()
            ->customer_order_pickup_id->toBe($order->pickups()->sole()->id);
    });

    it('refuse d\'utiliser plus que le crédit au compte', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        expect(fn () => $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => 44.98]], creditUsed: 70))
            ->toThrow(DomainException::class);

        expect($this->order->customer->fresh()->credit_balance)->toBe(50.0)
            ->and($this->order->payments()->count())->toBe(0);
    });

    it('propose le crédit dans le modal et l\'applique en premier', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->assertSee('Crédit client')
            ->assertSet('pickupCredit', '50.00')
            ->assertSet('pickupPayments.0.amount', '64.98')
            ->call('confirmPickup')
            ->assertHasNoErrors();

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
            ->and($this->order->customer->fresh()->credit_balance)->toBe(0.0);
    });

    it('accepte un paiement entièrement couvert par le crédit, sans mode de paiement', function () {
        $line = stockedLine($this->order, 1, 1, 20);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openPickupModal')
            ->call('continueToPayment')
            ->assertSet('pickupCredit', '23.00')
            ->set('pickupPayments.0.method_id', '')
            ->call('confirmPickup')
            ->assertHasNoErrors();

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
            ->and($this->order->customer->fresh()->credit_balance)->toBe(27.0);
    });
});

it('n\'affiche pas le crédit client quand le client n\'en a pas', function () {
    stockedLine($this->order, 1, 1, 100);

    Livewire::test(Show::class, ['order' => $this->order])
        ->call('openPickupModal')
        ->call('continueToPayment')
        ->assertDontSeeHtml('wire:model.blur="pickupCredit"');
});
