<?php

use App\Actions\CancelDelivery;
use App\Actions\ScheduleDelivery;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\ScheduleType;
use App\Livewire\CustomerOrders\Deliveries;
use App\Livewire\CustomerOrders\Show;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\DeliveryTruck;
use App\Models\Product;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use App\Support\DeliveryBooking;
use App\Support\Money;
use Livewire\Livewire;

/** Jeudi de la semaine en cours : il faut un quart publié pour y planifier. */
const DELIVERY_DATE = '2026-10-22';

beforeEach(function () {
    $this->travelTo('2026-10-20 09:00');

    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit', 'customers.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->order->customer->update(['address_civic' => '12', 'address_street' => 'rue Principale', 'address_city' => 'Rimouski', 'address_province' => 'QC', 'address_country' => 'CA', 'address_postal_code' => 'G5L1A1']);
    $this->cash = CustomerPaymentMethod::cash();

    $this->truck = DeliveryTruck::factory()->create(['store_id' => $this->order->store_id, 'capacity_per_period' => 2]);
    $this->driver = User::factory()->withRole('delivery')->create(['store_id' => $this->order->store_id]);
    Schedule::factory()->published()->forDate(DELIVERY_DATE)->create(['user_id' => $this->driver->id, 'start_time' => '08:00', 'end_time' => '17:00']);
});

/**
 * Commande entièrement payée : ligne en stock de 100 $ (114,98 $ taxes incluses).
 */
function paidLine(CustomerOrder $order, CustomerPaymentMethod $cash, int $quantity = 1): CustomerOrderLine
{
    $line = stockedLine($order, $quantity, $quantity, 100);
    $order->pickUp([], [['method_id' => $cash->id, 'amount' => $order->fresh()->balance_due]]);

    return $line->fresh();
}

function scheduleDelivery(CustomerOrder $order, array $quantities, string $date = DELIVERY_DATE, DeliveryPeriod $period = DeliveryPeriod::Am, bool $payOnDelivery = false): CustomerOrderDelivery
{
    return app(ScheduleDelivery::class)->handle($order, $quantities, $date, $period, $payOnDelivery);
}

describe('capacité par demi-journée', function () {
    it('compte les camions qui ont un livreur à l\'horaire pendant la demi-journée', function () {
        DeliveryTruck::factory()->create(['store_id' => $this->order->store_id, 'capacity_per_period' => 5]);
        Schedule::query()->update(['end_time' => '12:00']);

        // Un seul livreur : le plus gros camion part, le matin seulement.
        expect(DeliveryBooking::capacity($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Am))->toBe(5)
            ->and(DeliveryBooking::capacity($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Pm))->toBe(0);
    });

    it('n\'offre aucune place sans quart publié dans la semaine en cours, ni pendant une absence', function () {
        expect(DeliveryBooking::capacity($this->order->store_id, '2026-10-21', DeliveryPeriod::Am))->toBe(0);

        Schedule::query()->delete();
        Schedule::factory()->published()->forDate(DELIVERY_DATE)->absence(ScheduleType::Vacation)->create(['user_id' => $this->driver->id]);

        expect(DeliveryBooking::capacity($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Am))->toBe(0);
    });

    it('offre les camions actifs après la semaine en cours, avant la publication de l\'horaire', function () {
        DeliveryTruck::factory()->inactive()->create(['store_id' => $this->order->store_id, 'capacity_per_period' => 9]);

        expect(DeliveryBooking::capacity($this->order->store_id, '2026-10-27', DeliveryPeriod::Pm))->toBe(2);
    });

    it('ignore les livreurs d\'un autre magasin', function () {
        $this->driver->update(['store_id' => null]);

        expect(DeliveryBooking::capacity($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Am))->toBe(0);
    });
});

describe('vérification de l\'adresse', function () {
    it('ouvre le modal sur la vérification de l\'adresse du client', function () {
        paidLine($this->order, $this->cash);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->assertSet('showModal', true)
            ->assertSet('step', 'address')
            ->assertSee('rue Principale')
            ->call('confirmAddress')
            ->assertSet('step', 'items');
    });

    it('refuse de confirmer une adresse incomplète', function () {
        paidLine($this->order, $this->cash);
        $this->order->customer->update(['address_postal_code' => null]);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('confirmAddress')
            ->assertHasErrors('address')
            ->assertSet('step', 'address');
    });

    it('ouvre la fiche du client si l\'adresse n\'est pas vérifiée, puis reprend la vérification', function () {
        paidLine($this->order, $this->cash);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('editAddress')
            ->assertDispatched('open-customer-edit', id: $this->order->customer_id)
            ->assertSet('showModal', false)
            ->dispatch('customer-saved', id: $this->order->customer_id)
            ->assertSet('showModal', true)
            ->assertSet('step', 'address');
    });
});

describe('planification', function () {
    it('planifie une commande payée à une date et une demi-journée', function () {
        $line = paidLine($this->order, $this->cash, 2);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('confirmAddress')
            ->set("quantities.{$line->id}", 1)
            ->call('continueFromItems')
            ->assertSet('step', 'slot')
            ->call('pickSlot', DELIVERY_DATE, 'pm')
            ->set('notes', 'Appeler avant')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $delivery = $this->order->deliveries()->sole();

        expect($delivery)
            ->status->toBe(DeliveryStatus::Scheduled)
            ->scheduled_date->toDateString()->toBe(DELIVERY_DATE)
            ->period->toBe(DeliveryPeriod::Pm)
            ->address_street->toBe('rue Principale')
            ->address_postal_code->toBe('G5L1A1')
            ->address_verified_by->toBe($this->user->id)
            ->pay_on_delivery->toBeFalse()
            ->notes->toBe('Appeler avant')
            ->and($delivery->lines()->pluck('quantity', 'customer_order_line_id')->all())->toBe([$line->id => 1])
            ->and($this->driver->notifications()->sole()->data['title'])->toContain('Livraison planifiée');
    });

    it('garde l\'adresse vérifiée même si la fiche du client change ensuite', function () {
        $line = paidLine($this->order, $this->cash);
        $delivery = scheduleDelivery($this->order, [$line->id => 1]);

        $this->order->customer->update(['address_street' => 'rue Autre']);

        expect($delivery->fresh()->address_street)->toBe('rue Principale');
    });

    it('planifie des articles encore en commande s\'ils sont considérés payés', function () {
        $line = stockedLine($this->order, 0, 1, 100);
        $this->order->pickUp([], [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);

        $delivery = scheduleDelivery($this->order, [$line->id => 1]);

        expect($delivery->lines()->sole()->quantity)->toBe(1)
            ->and($line->fresh()->quantity_on_order)->toBe(1);
    });

    it('ne livre ni les services ni ce qui est déjà prévu dans une autre livraison', function () {
        $line = paidLine($this->order, $this->cash, 2);
        scheduleDelivery($this->order, [$line->id => 2]);

        expect(fn () => scheduleDelivery($this->order, [$line->id => 1], DELIVERY_DATE, DeliveryPeriod::Pm))
            ->toThrow(DomainException::class, 'La quantité à livrer dépasse ce qui reste à remettre au client.');
    });

    it('refuse une demi-journée complète ou déjà commencée', function () {
        $line = paidLine($this->order, $this->cash, 3);
        scheduleDelivery($this->order, [$line->id => 1]);
        scheduleDelivery($this->order, [$line->id => 1]);

        expect(fn () => scheduleDelivery($this->order, [$line->id => 1]))
            ->toThrow(DomainException::class, 'Plus aucune place de livraison pour cette demi-journée.')
            ->and(fn () => scheduleDelivery($this->order, [$line->id => 1], '2026-10-20', DeliveryPeriod::Am))
            ->toThrow(DomainException::class, 'Cette demi-journée est déjà commencée.');
    });

    it('les articles prévus dans une livraison ne se ramassent pas en magasin', function () {
        $line = paidLine($this->order, $this->cash, 2);
        scheduleDelivery($this->order, [$line->id => 1]);

        expect($this->order->pickableQuantities())->toBe([$line->id => 1])
            ->and(fn () => $this->order->pickUp([$line->id => 2], []))
            ->toThrow(DomainException::class);
    });
});

describe('solde à payer', function () {
    it('demande de régler la facture quand les articles à livrer ne sont pas payés', function () {
        $line = stockedLine($this->order, 1, 1, 100);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('confirmAddress')
            ->call('continueFromItems')
            ->assertSet('step', 'payment')
            ->assertSee('Réglez la facture avant la livraison')
            ->assertSee('114.98')
            ->call('settleInvoice')
            ->assertDispatched('open-pickup-modal');

        expect(fn () => scheduleDelivery($this->order, [$line->id => 1]))
            ->toThrow(DomainException::class, 'Réglez la facture avant de planifier la livraison : 114.98 $ à payer.');
    });

    it('ouvre l\'encaissement depuis la commande sans les articles prévus en livraison', function () {
        $line = paidLine($this->order, $this->cash, 2);
        scheduleDelivery($this->order, [$line->id => 2]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->dispatch('open-pickup-modal')
            ->assertSet('showPickupModal', true)
            ->assertSet('pickupQuantities', []);
    });

    it('permet à un directeur de choisir le paiement à la livraison', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        $this->user->givePermissionTo('customer_orders.deliver_unpaid');

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('confirmAddress')
            ->call('continueFromItems')
            ->call('choosePayOnDelivery')
            ->assertSet('step', 'slot')
            ->call('pickSlot', DELIVERY_DATE, 'am')
            ->call('confirm')
            ->assertHasNoErrors();

        expect($this->order->deliveries()->sole())
            ->pay_on_delivery->toBeTrue()
            ->pay_on_delivery_authorized_by->toBe($this->user->id)
            ->and($line->fresh()->quantity_reserved)->toBe(1);
    });

    it('refuse le paiement à la livraison sans la permission', function () {
        stockedLine($this->order, 1, 1, 100);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open')
            ->call('confirmAddress')
            ->call('continueFromItems')
            ->call('choosePayOnDelivery')
            ->assertForbidden();
    });
});

describe('annulation et report', function () {
    it('annule une livraison planifiée : ses articles et sa place se libèrent', function () {
        $line = paidLine($this->order, $this->cash);
        $delivery = scheduleDelivery($this->order, [$line->id => 1]);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('cancelDelivery', $delivery->id)
            ->assertDispatched('deliveries-updated');

        expect($delivery->fresh())
            ->status->toBe(DeliveryStatus::Cancelled)
            ->cancelled_by->toBe($this->user->id)
            ->and($this->order->deliverableQuantities())->toBe([$line->id => 1])
            ->and(DeliveryBooking::bookedCount($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Am))->toBe(0)
            ->and($this->driver->notifications()->pluck('data')->pluck('title')->last())->toContain('Livraison annulée');
    });

    it('refuse d\'annuler une livraison qui n\'est plus planifiée', function () {
        $line = paidLine($this->order, $this->cash);
        $delivery = scheduleDelivery($this->order, [$line->id => 1]);
        $delivery->update(['status' => DeliveryStatus::InTransit]);

        expect(fn () => app(CancelDelivery::class)->handle($delivery))
            ->toThrow(DomainException::class, 'Seule une livraison planifiée peut être annulée.');
    });

    it('reporte une livraison : elle est annulée et replanifiée avec ses articles et son adresse', function () {
        $line = paidLine($this->order, $this->cash, 2);
        $delivery = scheduleDelivery($this->order, [$line->id => 2]);
        $this->order->customer->update(['address_street' => 'rue Autre']);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('openReschedule', $delivery->id)
            ->assertSet('step', 'slot')
            ->call('pickSlot', '2026-10-27', 'pm')
            ->call('confirm')
            ->assertHasNoErrors();

        $replacement = $this->order->deliveries()->active()->sole();

        expect($delivery->fresh()->status)->toBe(DeliveryStatus::Cancelled)
            ->and($replacement)
            ->rescheduled_from_id->toBe($delivery->id)
            ->scheduled_date->toDateString()->toBe('2026-10-27')
            ->period->toBe(DeliveryPeriod::Pm)
            ->address_street->toBe('rue Principale')
            ->and($replacement->lines()->pluck('quantity', 'customer_order_line_id')->all())->toBe([$line->id => 2])
            ->and($this->driver->notifications()->pluck('data')->pluck('title')->last())->toContain('Livraison reportée');
    });

    it('garde la livraison d\'origine si la nouvelle demi-journée est complète', function () {
        $line = paidLine($this->order, $this->cash, 3);
        $delivery = scheduleDelivery($this->order, [$line->id => 1], DELIVERY_DATE, DeliveryPeriod::Pm);
        scheduleDelivery($this->order, [$line->id => 1]);
        scheduleDelivery($this->order, [$line->id => 1]);

        expect(fn () => app(ScheduleDelivery::class)->handle($this->order, [$line->id => 1], DELIVERY_DATE, DeliveryPeriod::Am, replacing: $delivery))
            ->toThrow(DomainException::class)
            ->and($delivery->fresh()->status)->toBe(DeliveryStatus::Scheduled);
    });
});

describe('lignes modifiées après la planification', function () {
    /**
     * Ligne « Commandé » de 2 articles (commande fournisseur envoyée), prévue dans une livraison payable à la livraison.
     *
     * @return array{0: CustomerOrderLine, 1: CustomerOrderDelivery}
     */
    function orderedLineToDeliver(CustomerOrder $order): array
    {
        $line = $order->addProduct(Product::factory()->create(), 2);
        SupplierOrder::sole()->send();
        $delivery = scheduleDelivery($order, [$line->id => 2], payOnDelivery: true);

        return [$line->fresh(), $delivery];
    }

    function lastDriverNotice(User $driver): array
    {
        return $driver->notifications()->where('type', DeliveryNotice::Type)->latest('id')->first()->data;
    }

    it('réduit la quantité prévue quand la ligne est réduite et avise les livreurs', function () {
        $line = paidLine($this->order, $this->cash, 2);
        $delivery = scheduleDelivery($this->order, [$line->id => 2]);

        $this->order->updateLine($line, 1, 0, Money::parse(100), null);

        expect($delivery->fresh()->status)->toBe(DeliveryStatus::Scheduled)
            ->and($delivery->lines()->sole()->quantity)->toBe(1)
            ->and(lastDriverNotice($this->driver))
            ->title->toContain('Livraison modifiée')
            ->body->toContain('1 × '.$line->label.' retiré(s)');
    });

    it('annule la livraison devenue vide quand sa ligne est retirée et libère sa place', function () {
        $line = paidLine($this->order, $this->cash);
        $delivery = scheduleDelivery($this->order, [$line->id => 1]);

        $this->order->removeLine($line);

        expect($delivery->fresh())
            ->status->toBe(DeliveryStatus::Cancelled)
            ->cancellation_reason->toBe('Plus aucun article à livrer')
            ->cancelled_by->toBe($this->user->id)
            ->and(DeliveryBooking::bookedCount($this->order->store_id, DELIVERY_DATE, DeliveryPeriod::Am))->toBe(0)
            ->and(lastDriverNotice($this->driver)['title'])->toContain('Livraison annulée');
    });

    it('réduit d\'abord les livraisons planifiées, puis celles en route', function () {
        $line = paidLine($this->order, $this->cash, 3);
        $scheduled = scheduleDelivery($this->order, [$line->id => 2]);
        $inTransit = scheduleDelivery($this->order, [$line->id => 1], DELIVERY_DATE, DeliveryPeriod::Pm);
        $inTransit->update(['status' => DeliveryStatus::InTransit]);

        $this->order->updateLine($line, 2, 0, Money::parse(100), null);

        expect($scheduled->lines()->sole()->quantity)->toBe(1)
            ->and($inTransit->lines()->sole()->quantity)->toBe(1);
    });

    it('annule la livraison d\'une ligne annulée avec frais', function () {
        [$line, $delivery] = orderedLineToDeliver($this->order);

        $this->order->cancelLineWithFee($line);

        expect($delivery->fresh()->status)->toBe(DeliveryStatus::Cancelled)
            ->and($this->order->deliveringQuantities())->toBe([]);
    });

    it('garde dans la livraison la partie reçue d\'une ligne annulée par le fournisseur', function () {
        [$line, $delivery] = orderedLineToDeliver($this->order);
        $line->product->inventoryStock()->increment('quantity_in_stock');
        $this->order->applySupplierReceipt($line, 1);

        $this->order->applySupplierCancellation($line->fresh(), 1);

        expect($line->fresh()->quantity)->toBe(1)
            ->and($delivery->fresh()->status)->toBe(DeliveryStatus::Scheduled)
            ->and($delivery->lines()->pluck('quantity', 'customer_order_line_id')->all())->toBe([$line->id => 1]);
    });

    it('reporte sur la ligne recommandée la quantité endommagée à la réception', function () {
        [$line, $delivery] = orderedLineToDeliver($this->order);
        $line->product->inventoryStock()->increment('quantity_in_stock');
        $this->order->applySupplierReceipt($line, 1);

        $this->order->applySupplierDamagedReceipt($line->fresh(), 1);

        $reorder = $this->order->lines()->whereKeyNot($line->id)->sole();

        expect($reorder->status)->toBe(CustomerOrderLineStatus::OnOrder)
            ->and($delivery->lines()->pluck('quantity', 'customer_order_line_id')->all())->toBe([$line->id => 1, $reorder->id => 1])
            ->and(lastDriverNotice($this->driver)['body'])->toContain('recommandé(s) au fournisseur');
    });

    it('retire de la livraison une ligne remplacée par un substitut du fournisseur', function () {
        [$line, $delivery] = orderedLineToDeliver($this->order);
        $replacement = SupplierOrderLine::factory()->forProduct()->create(['quantity' => 2]);

        $this->order->applySupplierSubstitution($line, 0, $replacement);

        expect($line->fresh()->product_id)->toBe($replacement->product_id)
            ->and($delivery->fresh()->status)->toBe(DeliveryStatus::Cancelled)
            ->and($this->order->deliverableQuantities())->toBe([$line->id => 2]);
    });
});

it('avise les livreurs quand un article à livrer est reçu avant la date de livraison', function () {
    $line = stockedLine($this->order, 0, 1, 100);
    $this->order->pickUp([], [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);
    scheduleDelivery($this->order, [$line->id => 1]);
    $line->product->inventoryStock()->increment('quantity_in_stock');

    $this->order->applySupplierReceipt($line, 1);

    expect($this->driver->notifications()->where('type', DeliveryNotice::Type)->pluck('data')->last())
        ->title->toContain('Articles reçus pour la livraison')
        ->body->toContain('1 × ')
        ->url->toBe(route('deliveries.index', ['date' => DELIVERY_DATE]));
});

it('n\'avise pas les livreurs pour un article reçu sans livraison prévue', function () {
    $line = stockedLine($this->order, 0, 1, 100);
    $line->product->inventoryStock()->increment('quantity_in_stock');

    $this->order->applySupplierReceipt($line, 1);

    expect($this->driver->notifications()->count())->toBe(0);
});

it('accorde le paiement à la livraison aux directeurs, pas aux vendeurs', function () {
    expect(User::factory()->withRole('manager')->create()->can('customer_orders.deliver_unpaid'))->toBeTrue()
        ->and(User::factory()->withRole('salesman')->create()->can('customer_orders.deliver_unpaid'))->toBeFalse()
        ->and($this->driver->can('deliveries.view'))->toBeTrue();
});

it('affiche les places et les livraisons de la semaine dans la grille des livraisons', function () {
    $line = paidLine($this->order, $this->cash);
    scheduleDelivery($this->order, [$line->id => 1]);
    $this->user->givePermissionTo('deliveries.view');

    $this->get(route('deliveries.index', ['date' => DELIVERY_DATE, 'store' => $this->order->store_id]))
        ->assertOk()
        ->assertSee('1 / 2')
        ->assertSee('#'.$this->order->id.' · '.$this->order->customer->lastname)
        ->assertSee('Rimouski');
});
