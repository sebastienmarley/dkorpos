<?php

use App\Actions\CancelDelivery;
use App\Actions\FailDelivery;
use App\Actions\RequestShipment;
use App\Actions\ScheduleDelivery;
use App\Actions\ShipShipment;
use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use App\Enums\DeliveryPeriod;
use App\Enums\SupplierType;
use App\Enums\UndeliveredReason;
use App\Models\customer;
use App\Models\CustomerNotice;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerPaymentMethod;
use App\Models\DeliveryTruck;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Customers\DeliveryUpdateNotice;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

/*
 * Avis au client sur sa livraison et son expédition (issue #136).
 */

beforeEach(function () {
    $this->travelTo('2026-10-20 09:00');
    Notification::fake();

    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit', 'customers.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->customer = $this->order->customer;
    $this->customer->update(['firstname' => 'Marguerite', 'email' => 'marguerite@client.test', 'address_civic' => '12', 'address_street' => 'rue Principale', 'address_city' => 'Rimouski', 'address_province' => 'QC', 'address_country' => 'CA', 'address_postal_code' => 'G5L1A1']);
    $this->order->store->update(['email' => 'magasin@dkor.test', 'shipping_warehouse_id' => $this->order->store_id]);

    DeliveryTruck::factory()->create(['store_id' => $this->order->store_id, 'capacity_per_period' => 2]);
    $driver = User::factory()->withRole('delivery')->create(['store_id' => $this->order->store_id]);
    Schedule::factory()->published()->forDate('2026-10-22')->create(['user_id' => $driver->id, 'start_time' => '08:00', 'end_time' => '17:00']);
    Schedule::factory()->published()->forDate('2026-10-23')->create(['user_id' => $driver->id, 'start_time' => '08:00', 'end_time' => '17:00']);

    $this->line = stockedLine($this->order, 1, 1, 100);
    $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);
});

/** Texte du courriel envoyé au client pour ce type d'avis. */
function sentDeliveryMail(customer $customer, CustomerNoticeType $type): string
{
    $sent = Notification::sent($customer, DeliveryUpdateNotice::class)->filter(fn (DeliveryUpdateNotice $notice): bool => $notice->type === $type);

    expect($sent)->toHaveCount(1);

    $mail = $sent->first()->toMail($customer);

    return $mail->subject."\n".implode("\n", $mail->introLines);
}

function scheduleOrderDelivery(CustomerOrder $order, int $lineId, string $date = '2026-10-22', DeliveryPeriod $period = DeliveryPeriod::Am, ?CustomerOrderDelivery $replacing = null): CustomerOrderDelivery
{
    return app(ScheduleDelivery::class)->handle($order, [$lineId => 1], $date, $period, replacing: $replacing);
}

it('avise le client de la date, de la période et de l\'adresse de sa livraison planifiée', function () {
    $delivery = scheduleOrderDelivery($this->order, $this->line->id);

    $mail = sentDeliveryMail($this->customer, CustomerNoticeType::DeliveryScheduled);
    expect($mail)->toContain($delivery->slotLabel())
        ->toContain('12 rue Principale, Rimouski QC G5L1A1');

    $notice = CustomerNotice::sole();
    expect($notice->status)->toBe(CustomerNoticeStatus::Sent)
        ->and($notice->subject->is($delivery))->toBeTrue()
        ->and($notice->customer_order_id)->toBe($this->order->id);
});

it('avise le client de la nouvelle date d\'une livraison reportée, sans avis d\'annulation', function () {
    $first = scheduleOrderDelivery($this->order, $this->line->id);
    $moved = scheduleOrderDelivery($this->order, $this->line->id, '2026-10-23', DeliveryPeriod::Pm, $first);

    expect(sentDeliveryMail($this->customer, CustomerNoticeType::DeliveryRescheduled))
        ->toContain($moved->slotLabel())
        ->toContain($first->slotLabel());
    expect(CustomerNotice::query()->where('type', CustomerNoticeType::DeliveryCancelled)->exists())->toBeFalse();
});

it('avise le client de l\'annulation de sa livraison', function () {
    $delivery = scheduleOrderDelivery($this->order, $this->line->id);

    app(CancelDelivery::class)->handle($delivery, 'Camion en panne');

    expect(sentDeliveryMail($this->customer, CustomerNoticeType::DeliveryCancelled))->toContain($delivery->slotLabel());
});

it('avise le client que sa livraison est en chemin au départ du camion', function () {
    $delivery = scheduleOrderDelivery($this->order, $this->line->id);
    $this->travelTo('2026-10-22 08:30');

    $this->order->loadDelivery($delivery, [$delivery->lines()->sole()->id => 1]);

    expect(sentDeliveryMail($this->customer, CustomerNoticeType::DeliveryOnTheWay))->toContain('en chemin');
});

it('n\'envoie aucun avis automatique pour une livraison non réussie', function () {
    $delivery = scheduleOrderDelivery($this->order, $this->line->id);
    $this->travelTo('2026-10-22 08:30');
    $this->order->loadDelivery($delivery, [$delivery->lines()->sole()->id => 1]);
    $noticesBefore = CustomerNotice::count();

    app(FailDelivery::class)->handle($delivery->refresh(), UndeliveredReason::CustomerAbsent);

    expect(CustomerNotice::count())->toBe($noticesBefore);
});

it('avise le client du transporteur et du numéro de suivi de son expédition', function () {
    $carrier = Supplier::factory()->create(['type' => SupplierType::Shipping, 'name' => 'Purolator']);
    $this->order->addShippingFee();
    if ($this->order->fresh()->balance_due->isPositive()) {
        $this->order->pickUp([], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);
    }
    $shipment = app(RequestShipment::class)->handle($this->order, [$this->line->id => 1]);
    $shipment->chooseCarrier($carrier->id, Money::parse(25));

    app(ShipShipment::class)->handle($shipment, [$shipment->lines()->sole()->id => 1], '1Z999');

    expect(sentDeliveryMail($this->customer, CustomerNoticeType::ShipmentSent))
        ->toContain('Purolator')
        ->toContain('1Z999');
});

it('n\'envoie aucun avis automatique au client qui les refuse', function () {
    $this->customer->update(['notifications_opt_out' => true]);

    scheduleOrderDelivery($this->order, $this->line->id);

    Notification::assertNotSentTo($this->customer, DeliveryUpdateNotice::class);
    expect(CustomerNotice::sole()->status)->toBe(CustomerNoticeStatus::OptedOut);
});

it('dirige la réponse du client vers le courriel du magasin', function () {
    $delivery = scheduleOrderDelivery($this->order, $this->line->id);

    $mail = (new DeliveryUpdateNotice($delivery, CustomerNoticeType::DeliveryScheduled))->toMail($this->customer);

    expect($mail->replyTo)->toBe([['magasin@dkor.test', $this->order->store->name]]);
});

it('n\'envoie rien si la planification échoue', function () {
    expect(fn () => scheduleOrderDelivery($this->order, $this->line->id, '2026-10-19'))->toThrow(DomainException::class);

    Notification::assertNothingSentTo($this->customer);
    expect(CustomerNotice::count())->toBe(0);
});
