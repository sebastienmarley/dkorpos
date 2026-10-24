<?php

use App\Actions\CompleteDelivery;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Livewire\Deliveries\Mine;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use App\Support\Money;
use Livewire\Livewire;

const ROUTE_DATE = '2026-10-27';

beforeEach(function () {
    $this->travelTo(ROUTE_DATE.' 07:30');

    $this->order = CustomerOrder::factory()->create();
    $this->order->customer->update(['address_civic' => '12', 'address_street' => 'rue Principale', 'address_city' => 'Rimouski', 'address_province' => 'QC', 'address_country' => 'CA', 'address_postal_code' => 'G5L1A1', 'cellphone' => '(418)555-1234']);
    $this->driver = User::factory()->withRole('delivery')->create(['store_id' => $this->order->store_id]);
    $this->actingAs($this->driver);
    $this->cash = CustomerPaymentMethod::cash();
});

/**
 * Ligne de produit dont le stock est entré par le journal (pour vérifier les invariants), au prix de 100 $.
 */
function journaledLine(CustomerOrder $order, int $inStock, int $quantity): CustomerOrderLine
{
    $product = Product::factory()->create(['cost' => Money::parse(40)]);

    if ($inStock > 0) {
        InventoryMovement::record($product, null, InventoryStatus::InStock, $inStock, InventoryMovementType::Adjustment);
    }

    $line = $order->addProduct($product, $quantity);
    $order->updateLine($line, $line->quantity_reserved, $line->quantity_on_order, Money::parse(100), null);

    return $line->fresh();
}

/**
 * Livraison planifiée de la commande, avec ses quantités par ligne.
 *
 * @param  array<int, int>  $quantities
 */
function plannedDelivery(CustomerOrder $order, array $quantities, DeliveryPeriod $period = DeliveryPeriod::Am, array $attributes = []): CustomerOrderDelivery
{
    $delivery = CustomerOrderDelivery::factory()->on(ROUTE_DATE, $period)->create(['customer_order_id' => $order->id, ...$attributes]);

    foreach ($quantities as $lineId => $quantity) {
        $delivery->lines()->create(['customer_order_line_id' => $lineId, 'quantity' => $quantity]);
    }

    return $delivery->fresh();
}

function settle(CustomerOrder $order, CustomerPaymentMethod $cash): void
{
    $order->pickUp([], [['method_id' => $cash->id, 'amount' => $order->fresh()->balance_due]]);
}

describe('menu « Mes livraisons »', function () {
    it('refuse le menu sans la permission de faire les livraisons', function () {
        $this->actingAs(User::factory()->withRole('salesman')->create())
            ->get(route('deliveries.mine'))
            ->assertForbidden();

        $this->actingAs($this->driver)->get(route('deliveries.mine'))->assertOk();
    });

    it('montre les livraisons de la journée par demi-journée, et celles qui attendent des articles', function () {
        $line = journaledLine($this->order, 1, 1);
        $waiting = journaledLine($this->order, 0, 1);
        settle($this->order, $this->cash);
        $morning = plannedDelivery($this->order, [$line->id => 1]);
        plannedDelivery($this->order, [$waiting->id => 1], DeliveryPeriod::Pm);
        CustomerOrderDelivery::factory()->on('2026-10-28', DeliveryPeriod::Am)->create(['customer_order_id' => $this->order->id]);

        Livewire::test(Mine::class)
            ->assertViewHas('byPeriod', fn ($byPeriod) => $byPeriod['am']->count() === 1 && $byPeriod['pm']->count() === 1)
            ->assertSee($morning->address_city)
            ->assertSee('En attente d\'articles')
            ->assertSee('Encore en commande : 1 × '.$waiting->label)
            ->call('nextDay')
            ->assertViewHas('byPeriod', fn ($byPeriod) => $byPeriod['am']->count() === 1 && $byPeriod['pm']->isEmpty());
    });

    it('ouvre une livraison avec l\'itinéraire, le téléphone et le montant à percevoir', function () {
        $line = journaledLine($this->order, 1, 1);
        $delivery = plannedDelivery($this->order, [$line->id => 1], attributes: ['pay_on_delivery' => true, 'notes' => 'Sonner deux fois']);

        Livewire::test(Mine::class)
            ->call('open', $delivery->id)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=', false)
            ->assertSee('tel:4185551234', false)
            ->assertSee('Sonner deux fois')
            ->assertSee('À percevoir : 114.98 $');
    });

    it('garde l\'ordre de la route choisi par un livreur, pour tous les livreurs du magasin', function () {
        $line = journaledLine($this->order, 3, 3);
        $first = plannedDelivery($this->order, [$line->id => 1]);
        $second = plannedDelivery($this->order, [$line->id => 1]);
        $third = plannedDelivery($this->order, [$line->id => 1]);

        Livewire::test(Mine::class)
            ->assertViewHas('byPeriod', fn ($byPeriod) => $byPeriod['am']->modelKeys() === [$first->id, $second->id, $third->id])
            ->call('move', $third->id, -1);

        $this->actingAs(User::factory()->withRole('delivery')->create(['store_id' => $this->order->store_id]));

        Livewire::test(Mine::class)
            ->assertViewHas('byPeriod', fn ($byPeriod) => $byPeriod['am']->modelKeys() === [$first->id, $third->id, $second->id]);
    });
});

describe('chargement du camion', function () {
    it('charge une ligne entière : elle passe en livraison et la livraison part en route', function () {
        $line = journaledLine($this->order, 2, 2);
        settle($this->order, $this->cash);
        $delivery = plannedDelivery($this->order, [$line->id => 2]);
        $deliveryLine = $delivery->lines()->sole();

        Livewire::test(Mine::class)
            ->call('open', $delivery->id)
            ->assertSet("loadQuantities.{$deliveryLine->id}", 2)
            ->call('depart')
            ->assertHasNoErrors();

        expect($line->fresh())
            ->status->toBe(CustomerOrderLineStatus::InDelivery)
            ->quantity_reserved->toBe(2)
            ->and($delivery->fresh())
            ->status->toBe(DeliveryStatus::InTransit)
            ->departed_by->toBe($this->driver->id)
            ->and($deliveryLine->fresh()->loaded_from_status)->toBe(CustomerOrderLineStatus::InStock)
            ->and($line->product->inventoryStock()->sole())
            ->quantity_reserved->toBe(0)
            ->quantity_in_delivery->toBe(2);

        expectInventoryIntegrity($line->product);
    });

    it('sépare une ligne chargée en partie, et retire de la livraison ce qui n\'est pas chargé', function () {
        $partial = journaledLine($this->order, 3, 3);
        $left = journaledLine($this->order, 1, 1);
        settle($this->order, $this->cash);
        $delivery = plannedDelivery($this->order, [$partial->id => 3, $left->id => 1]);
        [$partialLine, $leftLine] = $delivery->lines()->orderBy('id')->get()->all();

        $this->order->loadDelivery($delivery, [$partialLine->id => 2, $leftLine->id => 0]);

        $loaded = CustomerOrderLine::where('status', CustomerOrderLineStatus::InDelivery)->sole();

        expect($partial->fresh())
            ->quantity->toBe(1)
            ->quantity_reserved->toBe(1)
            ->status->toBe(CustomerOrderLineStatus::InStock)
            ->and($loaded->quantity)->toBe(2)
            ->and($delivery->lines()->sole())
            ->customer_order_line_id->toBe($loaded->id)
            ->quantity->toBe(2)
            ->and($this->order->pickableQuantities())->toBe([$partial->id => 1, $left->id => 1]);

        expectInventoryIntegrity($partial->product);
    });

    it('refuse de charger un article encore en commande, ou une livraison vide', function () {
        $line = journaledLine($this->order, 0, 1);
        $delivery = plannedDelivery($this->order, [$line->id => 1]);
        $deliveryLine = $delivery->lines()->sole();

        expect(fn () => $this->order->loadDelivery($delivery, [$deliveryLine->id => 1]))
            ->toThrow(DomainException::class, 'on peut charger au plus 0')
            ->and(fn () => $this->order->loadDelivery($delivery, [$deliveryLine->id => 0]))
            ->toThrow(DomainException::class, 'Cochez au moins un article chargé dans le camion.')
            ->and($delivery->fresh()->status)->toBe(DeliveryStatus::Scheduled);
    });
});

describe('remise au client', function () {
    it('livre les articles acceptés : ils sortent de l\'inventaire et la commande passe « Livrée »', function () {
        $line = journaledLine($this->order, 2, 2);
        settle($this->order, $this->cash);
        $delivery = plannedDelivery($this->order, [$line->id => 2]);
        $this->order->loadDelivery($delivery, [$delivery->lines()->sole()->id => 2]);

        Livewire::test(Mine::class)
            ->call('open', $delivery->id)
            ->call('complete')
            ->assertHasNoErrors();

        expect($line->fresh())
            ->status->toBe(CustomerOrderLineStatus::Delivered)
            ->quantity_reserved->toBe(0)
            ->delivered_at->not->toBeNull()
            ->and($delivery->fresh())
            ->status->toBe(DeliveryStatus::Delivered)
            ->delivered_by->toBe($this->driver->id)
            ->and($this->order->fresh()->status)->toBe(CustomerOrderStatus::Delivered)
            ->and($line->product->inventoryStock()->sole()->quantity_in_delivery)->toBe(0);

        expectInventoryIntegrity($line->product);
    });

    it('retourne en stock client les articles refusés et avise le vendeur', function () {
        $salesperson = User::factory()->withRole('salesman')->create();
        $this->order->syncSalespeople([['user_id' => $salesperson->id, 'percent' => 100]]);
        $line = journaledLine($this->order, 3, 3);
        settle($this->order, $this->cash);
        $delivery = plannedDelivery($this->order, [$line->id => 3]);
        $deliveryLine = $delivery->lines()->sole();
        $this->order->loadDelivery($delivery, [$deliveryLine->id => 3]);

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 2]))
            ->toThrow(DomainException::class, 'Indiquez la raison de chaque article refusé.');

        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 2], [$deliveryLine->id => 'Endommagé']);

        $refused = $line->fresh();
        $delivered = CustomerOrderLine::where('status', CustomerOrderLineStatus::Delivered)->sole();

        expect($refused)
            ->status->toBe(CustomerOrderLineStatus::InStock)
            ->quantity->toBe(1)
            ->quantity_reserved->toBe(1)
            ->and($delivered->quantity)->toBe(2)
            ->and($deliveryLine->fresh())
            ->accepted_quantity->toBe(2)
            ->refusal_reason->toBe('Endommagé')
            ->and($this->order->fresh()->status)->not->toBe(CustomerOrderStatus::Delivered)
            ->and($salesperson->notifications()->where('type', DeliveryNotice::Type)->sole()->data)
            ->title->toBe('Articles refusés à la livraison — commande #'.$this->order->id)
            ->body->toContain('1 × '.$line->label.' : Endommagé')
            ->url->toBe(route('customer-orders.show', $this->order));

        expectInventoryIntegrity($line->product);
    });

    it('exige le paiement à la livraison avant de remettre les articles', function () {
        $line = journaledLine($this->order, 1, 1);
        $delivery = plannedDelivery($this->order, [$line->id => 1], attributes: ['pay_on_delivery' => true]);
        $deliveryLine = $delivery->lines()->sole();
        $this->order->loadDelivery($delivery, [$deliveryLine->id => 1]);

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1]))
            ->toThrow(DomainException::class, 'Percevez le paiement avant de remettre les articles : 114.98 $ à payer.');

        settle($this->order, $this->cash);
        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1]);

        expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered);
    });

    it('passe « Livrée » une commande dont une partie est ramassée et l\'autre livrée', function () {
        $picked = journaledLine($this->order, 1, 1);
        $delivered = journaledLine($this->order, 1, 1);
        settle($this->order, $this->cash);
        $this->order->pickUp([$picked->id => 1], []);
        $delivery = plannedDelivery($this->order, [$delivered->id => 1]);
        $deliveryLine = $delivery->lines()->sole();
        $this->order->loadDelivery($delivery, [$deliveryLine->id => 1]);

        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1]);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Delivered);
    });

    it('ne remet que les livraisons en route', function () {
        $line = journaledLine($this->order, 1, 1);
        $delivery = plannedDelivery($this->order, [$line->id => 1]);

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$delivery->lines()->sole()->id => 1]))
            ->toThrow(DomainException::class, 'Seule une livraison en route peut être remise au client.');
    });
});

it('accorde le menu des livraisons aux livreurs et aux directeurs, pas aux vendeurs', function () {
    expect($this->driver->can('deliveries.handle'))->toBeTrue()
        ->and(User::factory()->withRole('manager')->create()->can('deliveries.handle'))->toBeTrue()
        ->and(User::factory()->withRole('salesman')->create()->can('deliveries.handle'))->toBeFalse();
});
