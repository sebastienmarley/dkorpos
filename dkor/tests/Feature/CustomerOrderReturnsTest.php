<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\InventoryMovementType;
use App\Enums\SupplierType;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->cash = CustomerPaymentMethod::cash();
});

it('reprend l\'article en stock lors d\'un échange et garde le payé au crédit de la commande', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    $this->order->returnLine($line, 1, refund: false);

    $order = $this->order->fresh();

    expect($line->fresh())
        ->status->toBe(CustomerOrderLineStatus::Returned)
        ->returned_at->not->toBeNull()
        ->and($order)
        ->subtotal->toBeMoney(0)
        ->amount_paid->toBeMoney(11498)
        ->balance_due->toBeMoney(-11498)
        ->and($order->payments()->count())->toBe(1)
        ->and($line->product->inventoryStocks()->sole()->quantity_in_stock)->toBe(1)
        ->and(InventoryUnit::where('product_id', $line->product_id)->whereNull('delivered_at')->count())->toBe(1)
        ->and(InventoryMovement::where('type', InventoryMovementType::CustomerReturn)->sole()->quantity)->toBe(1);
});

it('utilise le crédit de l\'échange pour le produit de remplacement', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);
    $this->order->returnLine($line, 1, refund: false);

    stockedLine($this->order, 1, 1, 150);

    expect($this->order->fresh())
        ->balance_due->toBeMoney(5748)
        ->status->toBe(CustomerOrderStatus::Pending);
});

it('rembourse un article retourné', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    expect($this->order->refundableFor($line, 1))->toBeMoney(11498);

    $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);

    $order = $this->order->fresh();

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
        ->and($order)->amount_paid->toBeMoney(0)->balance_due->toBeMoney(0)
        ->and($order->payments()->orderBy('id')->pluck('amount')->map->cents()->all())->toBe([11498, -11498])
        ->and($order->payments()->orderByDesc('id')->first()->type)->toBe(CustomerPaymentType::Refund)
        ->and($line->product->inventoryStocks()->sole()->quantity_in_stock)->toBe(1);
});

it('sépare la ligne retournée en partie', function () {
    $line = pickedUpLine($this->order, 2, 100, $this->cash);

    $this->order->returnLine($line, 1, refund: false);

    expect($line->fresh())->quantity->toBe(1)->status->toBe(CustomerOrderLineStatus::PickedUp)
        ->and($this->order->lines()->where('status', CustomerOrderLineStatus::Returned)->sole())
        ->quantity->toBe(1)
        ->unit_price->toBeMoney(10000)
        ->customer_order_pickup_id->toBe($line->customer_order_pickup_id)
        ->and($this->order->fresh()->status)->toBe(CustomerOrderStatus::PickedUp);
});

it('refuse un remboursement supérieur au montant remboursable', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    expect(fn () => $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => Money::parse(200)]]))
        ->toThrow(DomainException::class);

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
        ->and($line->product->inventoryStocks()->sole()->quantity_in_stock)->toBe(0);
});

it('refuse le retour d\'un article qui n\'a pas été remis au client', function () {
    $line = stockedLine($this->order, 1, 1, 100);

    expect(fn () => $this->order->returnLine($line, 1, refund: false))->toThrow(DomainException::class);
});

it('refuse une quantité retournée supérieure à la quantité remise', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    expect(fn () => $this->order->returnLine($line, 2, refund: false))->toThrow(DomainException::class);
});

describe('modal de retour', function () {
    it('propose le retour et le défectueux seulement pour un article remis', function () {
        $pickedUp = pickedUpLine($this->order, 1, 100, $this->cash);
        $inStock = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $pickedUp->id)
            ->assertSeeHtml('wire:click="openReturnModal('.$pickedUp->id.')"')
            ->assertSee('Défectueux')
            ->call('openLineModal', $inStock->id)
            ->assertDontSeeHtml('wire:click="openReturnModal(');
    });

    it('adapte le bouton au choix du client dès qu\'il le coche', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openReturnModal', $line->id)
            ->assertSeeHtml('wire:model.live="returnType"')
            ->set('returnType', 'exchange')
            ->assertSee('Reprendre l\'article')
            ->set('returnType', 'refund')
            ->assertSee('Continuer vers le remboursement');
    });

    it('fait un échange et revient à la commande', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->call('openReturnModal', $line->id)
            ->assertSet('showLineModal', false)
            ->assertSet('showReturnModal', true)
            ->set('returnType', 'exchange')
            ->call('continueReturn')
            ->assertHasNoErrors()
            ->assertSet('showReturnModal', false);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Returned);
    });

    it('reprend l\'article puis rembourse', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openReturnModal', $line->id)
            ->set('returnType', 'refund')
            ->call('continueReturn')
            ->assertSet('returnStep', 'refund')
            ->assertSet('returnRefundableCents', 11498)
            ->assertSet('returnRefunds.0.amount', '114.98')
            ->call('confirmReturnRefund')
            ->assertHasNoErrors()
            ->assertSet('showReturnModal', false);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
            ->and($this->order->fresh()->balance_due)->toBeMoney(0);
    });

    it('refuse d\'ouvrir le retour d\'un article non remis', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openReturnModal', $line->id)
            ->assertSet('showReturnModal', false);
    });
});

describe('crédit de la commande', function () {
    beforeEach(function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);
        $this->order->returnLine($line, 1, refund: false);
        $this->order->refresh();
    });

    it('rembourse le crédit sur le mode d\'origine et au compte du client', function () {
        expect($this->order->credit())->toBeMoney(11498);

        $this->order->refundCredit([
            ['method_id' => $this->cash->id, 'amount' => Money::parse(14.98)],
            ['method_id' => null, 'amount' => Money::parse(100)],
        ]);

        $order = $this->order->fresh();

        expect($order)->balance_due->toBeMoney(0)
            ->and($order->payments()->where('type', CustomerPaymentType::Refund)->sum('amount'))->toEqual(-1498)
            ->and($order->payments()->where('type', CustomerPaymentType::CreditTransfer)->sum('amount'))->toEqual(-10000)
            ->and($order->customer->fresh()->credit_balance)->toBeMoney(10000);
    });

    it('refuse de rembourser sur un mode qui n\'a pas payé la commande', function () {
        $visa = CustomerPaymentMethod::factory()->create(['name' => 'Visa']);

        expect(fn () => $this->order->refundCredit([['method_id' => $visa->id, 'amount' => Money::parse(10)]]))
            ->toThrow(DomainException::class, 'Le client n\'a rien payé en Visa');

        expect($this->order->fresh()->balance_due)->toBeMoney(-11498);
    });

    it('refuse de rembourser plus que ce que le mode a payé', function () {
        $visa = CustomerPaymentMethod::factory()->create(['name' => 'Visa']);
        $this->order->payments()->create(['customer_payment_method_id' => $visa->id, 'type' => CustomerPaymentType::Payment, 'amount' => Money::parse(5)]);

        expect(fn () => $this->order->refundCredit([['method_id' => $visa->id, 'amount' => Money::parse(10)]]))
            ->toThrow(DomainException::class, 'dépasse ce que le client a payé avec ce mode (5.00 $)');
    });

    it('n\'offre au remboursement que les modes qui ont payé la commande et le crédit au compte', function () {
        CustomerPaymentMethod::factory()->create(['name' => 'Visa']);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCreditRefundModal')
            ->assertSet('creditRefunds.0.method_id', (string) $this->cash->id)
            ->assertSee('Crédit au compte du client')
            ->assertDontSee('Visa (jusqu')
            ->set('creditRefunds.0.method_id', (string) CustomerPaymentMethod::where('name', 'Visa')->value('id'))
            ->call('refundCredit')
            ->assertHasErrors(['creditRefunds.0.method_id' => 'in'])
            ->set('creditRefunds.0.method_id', Show::STORE_CREDIT)
            ->call('refundCredit')
            ->assertHasNoErrors();

        expect($this->order->customer->fresh()->credit_balance)->toBeMoney(11498);
    });

    it('refuse un remboursement supérieur au crédit', function () {
        expect(fn () => $this->order->refundCredit([['method_id' => $this->cash->id, 'amount' => Money::parse(200)]]))
            ->toThrow(DomainException::class);

        expect($this->order->fresh()->balance_due)->toBeMoney(-11498);
    });

    it('porte le crédit au compte du client et solde la commande', function () {
        $credit = $this->order->transferCreditToCustomer();

        $order = $this->order->fresh();

        expect($credit)->toBeMoney(11498)
            ->and($order->customer->credit_balance)->toBeMoney(11498)
            ->and($order->balance_due)->toBeMoney(0)
            ->and($order->payments()->where('type', CustomerPaymentType::CreditTransfer)->sole())
            ->amount->toBeMoney(-11498)
            ->customer_payment_method_id->toBeNull();
    });

    it('porte au compte le reste après un remboursement partiel', function () {
        $this->order->refundCredit([['method_id' => $this->cash->id, 'amount' => Money::parse(50)]]);

        expect($this->order->fresh()->transferCreditToCustomer())->toBeMoney(6498)
            ->and($this->order->customer->fresh()->credit_balance)->toBeMoney(6498);
    });

    it('refuse de porter au compte une commande sans crédit', function () {
        $this->order->transferCreditToCustomer();

        expect(fn () => $this->order->fresh()->transferCreditToCustomer())->toThrow(DomainException::class);
    });

    it('propose les deux options et rembourse depuis la commande', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->assertSeeHtml('wire:click="openCreditRefundModal"')
            ->assertSeeHtml('wire:click="transferCreditToCustomer"')
            ->call('openCreditRefundModal')
            ->assertSet('creditRefunds.0.amount', '114.98')
            ->call('refundCredit')
            ->assertHasNoErrors()
            ->assertSet('showCreditRefundModal', false)
            ->assertDontSeeHtml('wire:click="openCreditRefundModal"');

        expect($this->order->fresh()->balance_due)->toBeMoney(0);
    });

    it('porte le crédit au compte depuis la commande', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->call('transferCreditToCustomer')
            ->assertSee('Crédit au compte : 114.98 $');

        expect($this->order->customer->fresh()->credit_balance)->toBeMoney(11498);
    });
});

describe('statut de la commande sans ligne qui compte', function () {
    it('passe à « Remboursée » une commande entièrement retournée et remboursée', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Refunded);
    });

    it('garde « En attente » une commande retournée tant que son crédit n\'est pas remboursé ou porté au compte', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);

        $this->order->returnLine($line, 1, refund: false);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Pending);

        $this->order->fresh()->transferCreditToCustomer();

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Refunded);
    });

    it('passe à « Annulée » une commande dont la seule ligne est annulée sans frais', function () {
        $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
        $line = $this->order->addProduct($product, 1);
        $line->fresh()->supplierOrderLine->order->send();
        $this->order->store->update(['cancellation_fee_percent' => 0]);

        $this->order->fresh()->cancelLineWithFee($line->fresh());

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Cancelled);
    });

    it('garde « En attente » une commande annulée tant que ses frais d\'annulation ne sont pas payés', function () {
        $this->order->store->update(['cancellation_fee_percent' => 15]);
        $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
        $line = $this->order->addProduct($product, 1);
        $this->order->updateLine($line, 0, 1, Money::parse(100), null);
        $line->fresh()->supplierOrderLine->order->send();

        $this->order->fresh()->cancelLineWithFee($line->fresh());

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Pending);

        $order = $this->order->fresh();
        $order->pickUp([], [['method_id' => $this->cash->id, 'amount' => $order->balance_due]]);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Cancelled);
    });

    it('rouvre « En attente » une commande remboursée qui reçoit un nouvel article', function () {
        $line = pickedUpLine($this->order, 1, 100, $this->cash);
        $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);

        stockedLine($this->order->fresh(), 1, 1, 50);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Pending);
    });
});
