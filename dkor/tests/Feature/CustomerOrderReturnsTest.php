<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\CustomerPaymentType;
use App\Enums\InventoryMovementType;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->cash = CustomerPaymentMethod::factory()->create(['name' => 'Comptant']);
});

/**
 * Ligne ramassée et entièrement payée (quantité au prix donné).
 */
function pickedUpLine(CustomerOrder $order, int $quantity, float $price, CustomerPaymentMethod $method): CustomerOrderLine
{
    $line = stockedLine($order, $quantity, $quantity, $price);
    $order->pickUp([$line->id => $quantity], [['method_id' => $method->id, 'amount' => $order->fresh()->balance_due]]);

    return $line->fresh();
}

it('reprend l\'article en stock lors d\'un échange et garde le payé au crédit de la commande', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    $this->order->returnLine($line, 1, refund: false);

    $order = $this->order->fresh();

    expect($line->fresh())
        ->status->toBe(CustomerOrderLineStatus::Returned)
        ->returned_at->not->toBeNull()
        ->and($order)
        ->subtotal->toBe(0.0)
        ->amount_paid->toBe(114.98)
        ->balance_due->toBe(-114.98)
        ->and($order->payments()->count())->toBe(1)
        ->and($line->product->inventoryStock()->sole()->quantity_in_stock)->toBe(1)
        ->and(InventoryUnit::where('product_id', $line->product_id)->whereNull('delivered_at')->count())->toBe(1)
        ->and(InventoryMovement::where('type', InventoryMovementType::CustomerReturn)->sole()->quantity)->toBe(1);
});

it('utilise le crédit de l\'échange pour le produit de remplacement', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);
    $this->order->returnLine($line, 1, refund: false);

    stockedLine($this->order, 1, 1, 150);

    expect($this->order->fresh())
        ->balance_due->toBe(57.48)
        ->status->toBe(CustomerOrderStatus::Pending);
});

it('rembourse un article retourné', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    expect($this->order->refundableFor($line, 1))->toBe(114.98);

    $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => 114.98]]);

    $order = $this->order->fresh();

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
        ->and($order)->amount_paid->toBe(0.0)->balance_due->toBe(0.0)
        ->and($order->payments()->orderBy('id')->pluck('amount')->all())->toBe([114.98, -114.98])
        ->and($order->payments()->orderByDesc('id')->first()->type)->toBe(CustomerPaymentType::Refund)
        ->and($line->product->inventoryStock()->sole()->quantity_in_stock)->toBe(1);
});

it('sépare la ligne retournée en partie', function () {
    $line = pickedUpLine($this->order, 2, 100, $this->cash);

    $this->order->returnLine($line, 1, refund: false);

    expect($line->fresh())->quantity->toBe(1)->status->toBe(CustomerOrderLineStatus::PickedUp)
        ->and($this->order->lines()->where('status', CustomerOrderLineStatus::Returned)->sole())
        ->quantity->toBe(1)
        ->unit_price->toBe(100.0)
        ->customer_order_pickup_id->toBe($line->customer_order_pickup_id)
        ->and($this->order->fresh()->status)->toBe(CustomerOrderStatus::PickedUp);
});

it('refuse un remboursement supérieur au montant remboursable', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    expect(fn () => $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => 200]]))
        ->toThrow(DomainException::class);

    expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
        ->and($line->product->inventoryStock()->sole()->quantity_in_stock)->toBe(0);
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
            ->assertSet('returnRefundable', 114.98)
            ->assertSet('returnRefunds.0.amount', '114.98')
            ->call('confirmReturnRefund')
            ->assertHasNoErrors()
            ->assertSet('showReturnModal', false);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Refunded)
            ->and($this->order->fresh()->balance_due)->toBe(0.0);
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

    it('rembourse le crédit sur plusieurs modes', function () {
        $visa = CustomerPaymentMethod::factory()->create(['name' => 'Visa']);

        expect($this->order->credit())->toBe(114.98);

        $this->order->refundCredit([
            ['method_id' => $this->cash->id, 'amount' => 14.98],
            ['method_id' => $visa->id, 'amount' => 100],
        ]);

        $order = $this->order->fresh();

        expect($order)->balance_due->toBe(0.0)->amount_paid->toBe(0.0)
            ->and($order->payments()->where('type', CustomerPaymentType::Refund)->sum('amount'))->toEqual(-114.98);
    });

    it('refuse un remboursement supérieur au crédit', function () {
        expect(fn () => $this->order->refundCredit([['method_id' => $this->cash->id, 'amount' => 200]]))
            ->toThrow(DomainException::class);

        expect($this->order->fresh()->balance_due)->toBe(-114.98);
    });

    it('porte le crédit au compte du client et solde la commande', function () {
        $credit = $this->order->transferCreditToCustomer();

        $order = $this->order->fresh();

        expect($credit)->toBe(114.98)
            ->and($order->customer->credit_balance)->toBe(114.98)
            ->and($order->balance_due)->toBe(0.0)
            ->and($order->payments()->where('type', CustomerPaymentType::CreditTransfer)->sole())
            ->amount->toBe(-114.98)
            ->customer_payment_method_id->toBeNull();
    });

    it('porte au compte le reste après un remboursement partiel', function () {
        $this->order->refundCredit([['method_id' => $this->cash->id, 'amount' => 50]]);

        expect($this->order->fresh()->transferCreditToCustomer())->toBe(64.98)
            ->and($this->order->customer->fresh()->credit_balance)->toBe(64.98);
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

        expect($this->order->fresh()->balance_due)->toBe(0.0);
    });

    it('porte le crédit au compte depuis la commande', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->call('transferCreditToCustomer')
            ->assertSee('Crédit au compte : 114.98 $');

        expect($this->order->customer->fresh()->credit_balance)->toBe(114.98);
    });
});
