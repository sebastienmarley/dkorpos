<?php

use App\Actions\CancelDelivery;
use App\Actions\RequestShipment;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Livewire\Catalog\Services;
use App\Livewire\CustomerOrders\Deliveries;
use App\Livewire\Deliveries\Index as DeliveriesIndex;
use App\Livewire\Receptions\Create as ReceptionCreate;
use App\Livewire\Stores\Show as StoreShow;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\DeliveryNotice;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit', 'customers.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->order->customer->update(['address_civic' => '12', 'address_street' => 'rue Principale', 'address_city' => 'Gaspé', 'address_province' => 'QC', 'address_country' => 'CA', 'address_postal_code' => 'G4X1A1']);
    $this->cash = CustomerPaymentMethod::cash();

    $this->warehouseClerk = User::factory()->withRole('warehouse')->create();
    $this->salesman = User::factory()->withRole('salesman')->create();
});

/** Encaisse tout le solde de la commande. */
function payBalance(CustomerOrder $order, CustomerPaymentMethod $cash): void
{
    $order->pickUp([], [['method_id' => $cash->id, 'amount' => $order->fresh()->balance_due]]);
}

/** Ligne en commande chez le fournisseur, sur une commande fournisseur envoyée. */
function orderedLine(CustomerOrder $order): CustomerOrderLine
{
    $line = stockedLine($order, 0, 1, 100);
    $supplierOrder = $line->supplierOrderLine->order;
    $supplierOrder->markPending();
    $supplierOrder->send();

    return $line->fresh();
}

function shipmentNotices(User $user): Collection
{
    return $user->notifications()->where('type', DeliveryNotice::Type)->pluck('data');
}

it('crée le service « Frais d\'expédition », interne, avec les données de référence', function () {
    expect(Service::where('code', Service::SHIPPING_FEE)->sole())
        ->is_internal->toBeTrue()
        ->is_active->toBeTrue();
});

describe('demande d\'expédition', function () {
    it('vérifie l\'adresse, ajoute les frais d\'expédition et exige le paiement des articles et des frais', function () {
        Service::shippingFee()->update(['selling_price' => Money::parse(15)]);
        $line = stockedLine($this->order, 1, 1, 100);
        payBalance($this->order, $this->cash);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open', 'shipping')
            ->assertSet('step', 'address')
            ->call('confirmAddress')
            ->assertSet('step', 'items')
            ->assertSet('addShippingFee', true)
            ->call('continueFromItems')
            ->assertSet('step', 'payment')
            ->assertSee('Réglez la facture avant l\'expédition')
            ->assertSee('17.24')
            ->assertDontSee('Paiement à la livraison');

        expect($this->order->shippingFeeLines()->sole())
            ->unit_price->toBeMoney(1500)
            ->and(fn () => app(RequestShipment::class)->handle($this->order, [$line->id => 1]))
            ->toThrow(DomainException::class, 'Réglez la facture avant de demander l\'expédition : 17.24 $ à payer (les articles expédiés doivent être payés à 100 %).');
    });

    it('n\'accepte pas le paiement à l\'expédition, même pour un directeur', function () {
        stockedLine($this->order, 1, 1, 100);
        $this->user->givePermissionTo('customer_orders.deliver_unpaid');

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open', 'shipping')
            ->call('confirmAddress')
            ->set('addShippingFee', false)
            ->call('continueFromItems')
            ->assertSet('step', 'payment')
            ->call('choosePayOnDelivery')
            ->assertHasErrors('payment')
            ->assertSet('step', 'payment');
    });

    it('exige 100 % des articles expédiés, même avec le dépôt couvert sur le reste', function () {
        $shipped = stockedLine($this->order, 1, 1, 100);
        stockedLine($this->order, 1, 1, 100);
        $this->order->pickUp([], [['method_id' => $this->cash->id, 'amount' => Money::parse(100)]]);

        // 114,98 $ (100 % de l'article expédié) + 30 % de l'autre (taxes arrondies sur le total) − 100 $ payé
        expect($this->order->amountRequiredForShipment([$shipped->id => 1]))->toBeMoney(4947);
    });

    it('demande l\'expédition d\'articles en stock : l\'entrepôt et les directeurs sont avisés tout de suite', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        payBalance($this->order, $this->cash);
        $manager = User::factory()->withRole('manager')->create();

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open', 'shipping')
            ->call('confirmAddress')
            ->set('addShippingFee', false)
            ->call('continueFromItems')
            ->assertSet('step', 'confirm')
            ->set('notes', 'Fragile')
            ->call('requestShipment')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $shipment = $this->order->deliveries()->sole();

        expect($shipment)
            ->method->toBe(DeliveryMethod::Shipping)
            ->status->toBe(DeliveryStatus::Scheduled)
            ->scheduled_date->toBeNull()
            ->period->toBeNull()
            ->address_city->toBe('Gaspé')
            ->notes->toBe('Fragile')
            ->ready_at->not->toBeNull()
            ->statusLabel()->toBe('À expédier')
            ->and($shipment->lines()->pluck('quantity', 'customer_order_line_id')->all())->toBe([$line->id => 1])
            ->and(shipmentNotices($this->warehouseClerk)->sole())
            ->title->toBe('Nouvelle expédition à faire')
            ->url->toBe(route('deliveries.index').'#expeditions')
            ->and(shipmentNotices($manager))->toHaveCount(1)
            ->and(shipmentNotices($this->salesman))->toBeEmpty();
    });

    it('signale les articles trop gros, trop lourds ou sans dimensions, sans bloquer l\'expédition', function () {
        $this->order->store->update(['shipping_max_length' => 48, 'shipping_max_weight' => 50]);
        $large = $this->order->addProduct(Product::factory()->create(['length' => 60, 'width' => 20, 'height' => 20, 'weight' => 10]));
        $heavy = $this->order->addProduct(Product::factory()->create(['length' => 20, 'width' => 20, 'height' => 20, 'weight' => 80]));
        $unknown = $this->order->addProduct(Product::factory()->create(['length' => null, 'width' => null, 'height' => null, 'weight' => null]));
        $small = $this->order->addProduct(Product::factory()->create(['length' => 20, 'width' => 10, 'height' => 5, 'weight' => 3]));

        $warnings = Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('open', 'shipping')
            ->call('confirmAddress')
            ->instance()
            ->getShippingWarnings();

        expect($warnings)->toHaveKeys([$large->id, $heavy->id, $unknown->id])
            ->not->toHaveKey($small->id)
            ->and($warnings[$large->id])->toContain('Trop grand')
            ->and($warnings[$heavy->id])->toContain('Trop lourd')
            ->and($warnings[$unknown->id])->toContain('Dimensions inconnues');
    });
});

describe('réception des articles à expédier', function () {
    it('indique à la réception qu\'un article est attendu pour une expédition', function () {
        $line = orderedLine($this->order);
        payBalance($this->order, $this->cash);
        app(RequestShipment::class)->handle($this->order, [$line->id => 1]);
        $this->user->givePermissionTo('receptions.create');
        $supplierLine = $line->supplierOrderLine;

        Livewire::test(ReceptionCreate::class)
            ->set('supplierId', $supplierLine->order->supplier_id)
            ->set('orderSearch', $supplierLine->order->number)
            ->assertSee('Pour expédition — commande #'.$this->order->id)
            ->set("selected.{$supplierLine->id}", true)
            ->assertDispatched('toast-show');
    });

    it('avise une seule fois quand la réception met en main le dernier article de l\'expédition', function () {
        $line = orderedLine($this->order);
        payBalance($this->order, $this->cash);
        $shipment = app(RequestShipment::class)->handle($this->order, [$line->id => 1]);

        expect($shipment->ready_at)->toBeNull()
            ->and(shipmentNotices($this->warehouseClerk))->toBeEmpty();

        $supplierLine = $line->supplierOrderLine;
        Reception::start($supplierLine->order->supplier, [$supplierLine->id => ['quantity' => 1]])->complete();

        expect($shipment->fresh()->ready_at)->not->toBeNull()
            ->and(shipmentNotices($this->warehouseClerk)->pluck('title')->all())->toBe(['Nouvelle expédition à faire']);
    });

    it('n\'avise pas tant qu\'un article de l\'expédition est encore en commande', function () {
        $received = orderedLine($this->order);
        $waiting = orderedLine($this->order);
        payBalance($this->order, $this->cash);
        app(RequestShipment::class)->handle($this->order, [$received->id => 1, $waiting->id => 1]);

        $supplierLine = $received->supplierOrderLine;
        Reception::start($supplierLine->order->supplier, [$supplierLine->id => ['quantity' => 1]])->complete();

        expect(shipmentNotices($this->warehouseClerk))->toBeEmpty();
    });
});

describe('suivi des expéditions', function () {
    it('annule une expédition et avise l\'entrepôt; elle ne se reporte pas', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        payBalance($this->order, $this->cash);
        $shipment = app(RequestShipment::class)->handle($this->order, [$line->id => 1]);

        Livewire::test(Deliveries::class, ['order' => $this->order])
            ->call('openReschedule', $shipment->id)
            ->assertSet('showModal', false);

        app(CancelDelivery::class)->handle($shipment);

        expect($shipment->fresh()->status)->toBe(DeliveryStatus::Cancelled)
            ->and(shipmentNotices($this->warehouseClerk)->pluck('title')->last())->toBe('Expédition annulée')
            ->and($this->order->deliverableQuantities())->toBe([$line->id => 1]);
    });

    it('permet de livrer, d\'expédier et de ramasser des articles différents de la même commande', function () {
        $shipped = stockedLine($this->order, 1, 1, 100);
        $picked = stockedLine($this->order, 1, 1, 100);
        payBalance($this->order, $this->cash);

        app(RequestShipment::class)->handle($this->order, [$shipped->id => 1]);

        expect($this->order->pickableQuantities())->toBe([$picked->id => 1])
            ->and($this->order->deliverableQuantities())->toBe([$picked->id => 1]);
    });

    it('liste les expéditions à faire du magasin sur la page des livraisons', function () {
        $line = stockedLine($this->order, 1, 1, 100);
        payBalance($this->order, $this->cash);
        app(RequestShipment::class)->handle($this->order, [$line->id => 1]);
        $this->user->givePermissionTo('deliveries.view');

        Livewire::withQueryParams(['store' => $this->order->store_id])
            ->test(DeliveriesIndex::class)
            ->assertSee('Expéditions')
            ->assertSee('Gaspé')
            ->assertSee('À faire — articles en main');
    });
});

describe('gestion', function () {
    it('enregistre les limites d\'expédition dans l\'onglet Expédition du magasin', function () {
        $this->user->givePermissionTo(['stores.view', 'stores.edit']);

        Livewire::test(StoreShow::class, ['store' => $this->order->store])
            ->set('shippingMaxLength', '48,5')
            ->set('shippingMaxWeight', '')
            ->call('saveShippingLimits')
            ->assertHasNoErrors();

        expect($this->order->store->fresh())
            ->shipping_max_length->toBe(48.5)
            ->shipping_max_weight->toBeNull();
    });

    it('garde le service « Frais d\'expédition » interne et actif', function () {
        $this->user->givePermissionTo(['services.view', 'services.edit']);
        $service = Service::shippingFee();

        Livewire::test(Services::class)
            ->call('openEdit', $service->id)
            ->set('sellingPrice', '12.50')
            ->set('isActive', false)
            ->set('isInternal', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($service->fresh())
            ->selling_price->toBeMoney(1250)
            ->is_active->toBeTrue()
            ->is_internal->toBeTrue();
    });

    it('accorde la gestion des expéditions aux commis d\'entrepôt et aux directeurs', function () {
        expect($this->warehouseClerk->can('shipments.manage'))->toBeTrue()
            ->and(User::factory()->withRole('manager')->create()->can('shipments.manage'))->toBeTrue()
            ->and($this->salesman->can('shipments.manage'))->toBeFalse();
    });
});

it('avise l\'entrepôt d\'une expédition réduite, puis à faire quand ce qui reste est en main', function () {
    $line = stockedLine($this->order, 1, 2, 100);
    payBalance($this->order, $this->cash);
    $shipment = app(RequestShipment::class)->handle($this->order, [$line->id => 2]);

    $this->order->updateLine($line->fresh(), 1, 0, Money::parse(100), null);

    expect(CustomerOrderDelivery::find($shipment->id)->lines()->sole()->quantity)->toBe(1)
        ->and(shipmentNotices($this->warehouseClerk)->pluck('title')->all())->toBe(['Expédition modifiée', 'Nouvelle expédition à faire']);
});
