<?php

use App\Actions\NotifyCustomerPickupReady;
use App\Enums\CustomerNoticeType;
use App\Enums\SupplierOrderStatus;
use App\Models\CustomerNotice;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\Reception;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CustomerOrderHandedOverNotice;
use App\Notifications\Customers\PickupReadyNotice;
use Illuminate\Support\Facades\Notification;

/*
 * Commande prête à ramasser, et vendeurs avisés quand leur client a tout reçu (issue #137).
 */

beforeEach(function () {
    Notification::fake();

    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->order->customer->update(['firstname' => 'Marguerite', 'email' => 'marguerite@client.test']);
    $this->order->store->update([
        'name' => 'Succursale Rimouski', 'address_civic' => '25', 'address_street' => 'rue Saint-Germain', 'address_city' => 'Rimouski',
        'opening_hours' => ['sunday' => ['open' => false, 'from' => '09:00', 'to' => '17:00'], 'saturday' => ['open' => true, 'from' => '10:00', 'to' => '16:00']],
    ]);
});

/** Ligne commandée chez le fournisseur pour le client (rien en stock), au prix de 100 $. */
function lineOnOrder(CustomerOrder $order, int $quantity = 1): CustomerOrderLine
{
    return stockedLine($order, 0, $quantity, 100);
}

/** Reçoit chez le fournisseur la quantité donnée de la ligne commandée pour le client (commande envoyée au besoin). */
function receiveForCustomer(CustomerOrderLine $line, int $quantity): void
{
    $supplierOrder = $line->fresh()->supplierOrderLine->order;

    if ($supplierOrder->status === SupplierOrderStatus::Draft) {
        $supplierOrder->markPending();
        $supplierOrder->fresh()->send();
    }

    Reception::record($supplierOrder->supplier, [$line->supplier_order_line_id => ['quantity' => $quantity]]);
}

function pickupReadyMail(CustomerOrder $order): string
{
    $sent = Notification::sent($order->customer, PickupReadyNotice::class);

    expect($sent)->toHaveCount(1);

    $mail = $sent->first()->toMail($order->customer);

    return $mail->subject."\n".implode("\n", $mail->introLines);
}

it('avise le client que sa commande est prête quand le dernier article commandé est reçu', function () {
    stockedLine($this->order, 1, 1, 50);
    $ordered = lineOnOrder($this->order);

    receiveForCustomer($ordered, 1);

    expect(pickupReadyMail($this->order))
        ->toContain('prête à ramasser')
        ->toContain('Succursale Rimouski')
        ->toContain('25 rue Saint-Germain')
        ->toContain('Samedi : 10:00 à 16:00')
        ->toContain('Dimanche : fermé')
        ->toContain('Solde à payer au ramassage : '.$this->order->fresh()->balance_due->format());
    expect(CustomerNotice::sole()->type)->toBe(CustomerNoticeType::PickupReady);
});

it('n\'avise pas le client tant qu\'un article est encore en commande', function () {
    $first = lineOnOrder($this->order);
    lineOnOrder($this->order);

    receiveForCustomer($first, 1);

    Notification::assertNotSentTo($this->order->customer, PickupReadyNotice::class);
    expect(CustomerNotice::count())->toBe(0);
});

it('n\'avise pas le client quand une partie seulement de la ligne est reçue', function () {
    $ordered = lineOnOrder($this->order, 2);

    receiveForCustomer($ordered, 1);
    Notification::assertNotSentTo($this->order->customer, PickupReadyNotice::class);

    receiveForCustomer($ordered, 1);
    expect(pickupReadyMail($this->order))->toContain('prête à ramasser');
});

it('n\'avise pas le client d\'un ramassage quand une livraison est prévue', function () {
    $ordered = lineOnOrder($this->order);
    CustomerOrderDelivery::factory()->create(['customer_order_id' => $this->order->id]);

    receiveForCustomer($ordered, 1);

    Notification::assertNotSentTo($this->order->customer, PickupReadyNotice::class);
});

it('n\'envoie qu\'un avis de ramassage par commande', function () {
    $ordered = lineOnOrder($this->order);
    receiveForCustomer($ordered, 1);

    app(NotifyCustomerPickupReady::class)->handle($this->order);

    Notification::assertSentToTimes($this->order->customer, PickupReadyNotice::class, 1);
});

it('dit au client que sa commande est payée quand il n\'y a plus de solde', function () {
    $ordered = lineOnOrder($this->order);
    $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

    receiveForCustomer($ordered, 1);

    expect(pickupReadyMail($this->order))->toContain('payée au complet');
});

it('avise les vendeurs quand leur client a ramassé toute sa commande', function () {
    $salesperson = User::factory()->create();
    $this->order->syncSalespeople([['user_id' => $salesperson->id, 'percent' => 50], ['user_id' => $this->user->id, 'percent' => 50]]);
    $line = stockedLine($this->order, 2, 2, 100);
    $cash = CustomerPaymentMethod::cash();

    $this->order->pickUp([$line->id => 1], [['method_id' => $cash->id, 'amount' => $this->order->fresh()->balance_due]]);
    Notification::assertNotSentTo($salesperson, CustomerOrderHandedOverNotice::class);

    $this->order->pickUp([$this->order->lines()->where('status', '!=', 'picked_up')->sole()->id => 1], []);

    Notification::assertSentTo($salesperson, CustomerOrderHandedOverNotice::class, function (CustomerOrderHandedOverNotice $notice) use ($salesperson): bool {
        return $notice->toArray($salesperson)['title'] === 'Commande #'.$this->order->id.' ramassée au complet';
    });
    Notification::assertNotSentTo($this->user, CustomerOrderHandedOverNotice::class);
});
