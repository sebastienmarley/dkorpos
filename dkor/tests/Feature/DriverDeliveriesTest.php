<?php

use App\Actions\CompleteDelivery;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\UndeliveredReason;
use App\Livewire\Accounting\PaymentMethods;
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
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const ROUTE_DATE = '2026-10-27';

beforeEach(function () {
    $this->travelTo(ROUTE_DATE.' 07:30');
    Storage::fake('local');

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
        InventoryMovement::record($product, $order->stockStoreId(), null, InventoryStatus::InStock, $inStock, InventoryMovementType::Adjustment);
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

/** Signature du client : une image PNG de 1 × 1, en URL de données. */
function signaturePng(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
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
            ->and($line->product->inventoryStocks()->sole())
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
            ->call('startConfirmation')
            ->call('confirmReceipt')
            ->assertSet('completionStep', 'signature')
            ->call('complete', signaturePng())
            ->assertHasNoErrors();

        expect($line->fresh())
            ->status->toBe(CustomerOrderLineStatus::Delivered)
            ->quantity_reserved->toBe(0)
            ->delivered_at->not->toBeNull()
            ->and($delivery->fresh())
            ->status->toBe(DeliveryStatus::Delivered)
            ->delivered_by->toBe($this->driver->id)
            ->and($this->order->fresh()->status)->toBe(CustomerOrderStatus::Delivered)
            ->and($line->product->inventoryStocks()->sole()->quantity_in_delivery)->toBe(0);

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

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 2], [], 'Client', signaturePng()))
            ->toThrow(DomainException::class, 'Indiquez la raison de chaque article refusé.');

        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 2], [$deliveryLine->id => 'Endommagé'], 'Client', signaturePng());

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

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1], [], 'Client', signaturePng()))
            ->toThrow(DomainException::class, 'Percevez le paiement avant de remettre les articles : 114.98 $ à payer.');

        settle($this->order, $this->cash);
        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1], [], 'Client', signaturePng());

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

        app(CompleteDelivery::class)->handle($delivery, [$deliveryLine->id => 1], [], 'Client', signaturePng());

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::Delivered);
    });

    it('ne remet que les livraisons en route', function () {
        $line = journaledLine($this->order, 1, 1);
        $delivery = plannedDelivery($this->order, [$line->id => 1]);

        expect(fn () => app(CompleteDelivery::class)->handle($delivery, [$delivery->lines()->sole()->id => 1], [], 'Client', signaturePng()))
            ->toThrow(DomainException::class, 'Seule une livraison en route peut être remise au client.');
    });
});

it('accorde le menu des livraisons aux livreurs et aux directeurs, pas aux vendeurs', function () {
    expect($this->driver->can('deliveries.handle'))->toBeTrue()
        ->and(User::factory()->withRole('manager')->create()->can('deliveries.handle'))->toBeTrue()
        ->and(User::factory()->withRole('salesman')->create()->can('deliveries.handle'))->toBeFalse();
});

describe('signature du client (#93)', function () {
    /** Livraison en route, payée, d'une ligne de 2 unités et d'une ligne d'une unité. */
    beforeEach(function () {
        $this->salesperson = User::factory()->withRole('salesman')->create(['store_id' => $this->order->store_id]);
        $this->order->syncSalespeople([['user_id' => $this->salesperson->id, 'percent' => 100]]);
        $this->pair = journaledLine($this->order, 2, 2);
        $this->single = journaledLine($this->order, 1, 1);
        settle($this->order, $this->cash);
        $this->delivery = plannedDelivery($this->order, [$this->pair->id => 2, $this->single->id => 1]);
        [$this->pairLine, $this->singleLine] = $this->delivery->lines()->orderBy('id')->get()->all();
        $this->order->loadDelivery($this->delivery, [$this->pairLine->id => 2, $this->singleLine->id => 1]);
    });

    it('coche et décoche tous les articles avec « Tout accepter »', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->call('startConfirmation')
            ->assertSet('completionStep', 'checklist')
            ->assertSee('Tout accepter')
            ->call('toggleAll', false)
            ->assertSet('acceptedChecks', [$this->pairLine->id => false, $this->singleLine->id => false])
            ->call('toggleAll', true)
            ->assertSet('acceptedChecks', [$this->pairLine->id => true, $this->singleLine->id => true]);
    });

    it('demande de cocher au moins un article, et une raison pour chaque article refusé', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->call('startConfirmation')
            ->call('toggleAll', false)
            ->call('confirmReceipt')
            ->assertHasErrors('checklist')
            ->set("acceptedChecks.{$this->pairLine->id}", true)
            ->call('confirmReceipt')
            ->assertHasErrors("refusalReasons.{$this->singleLine->id}")
            ->set("refusalReasons.{$this->singleLine->id}", 'Mauvais article')
            ->call('confirmReceipt')
            ->assertHasNoErrors()
            ->assertSet('completionStep', 'signature')
            ->assertSet('signerName', $this->order->customer->firstname.' '.$this->order->customer->lastname);
    });

    it('conserve la signature avec le nom, la date et les articles acceptés et refusés', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->call('startConfirmation')
            ->set("acceptedChecks.{$this->singleLine->id}", false)
            ->set("refusalReasons.{$this->singleLine->id}", 'Endommagé')
            ->call('confirmReceipt')
            ->set('signerName', 'Marie Tremblay')
            ->call('complete', signaturePng())
            ->assertHasNoErrors();

        $delivery = $this->delivery->fresh();

        expect($delivery)
            ->status->toBe(DeliveryStatus::Delivered)
            ->signed_by_name->toBe('Marie Tremblay')
            ->signed_at->not->toBeNull()
            ->and(Storage::disk('local')->exists($delivery->signature_path))->toBeTrue()
            ->and($this->pairLine->fresh()->accepted_quantity)->toBe(2)
            ->and($this->singleLine->fresh())
            ->accepted_quantity->toBe(0)
            ->refusal_reason->toBe('Endommagé')
            ->and($this->salesperson->notifications()->sole()->data['body'])->toContain('Endommagé');

        $this->actingAs($this->salesperson)
            ->get(route('deliveries.signature', $delivery))
            ->assertOk();

        expectInventoryIntegrity($this->single->product);
    });

    it('range et sert la signature sur le disque configuré, comme le stockage objet de la production', function () {
        config(['deliveries.signature_disk' => 's3']);
        Storage::fake('s3');

        app(CompleteDelivery::class)->handle($this->delivery, [$this->pairLine->id => 2, $this->singleLine->id => 1], [], 'Marie Tremblay', signaturePng());
        $path = $this->delivery->fresh()->signature_path;

        expect(Storage::disk('s3')->exists($path))->toBeTrue()
            ->and(Storage::disk('local')->exists($path))->toBeFalse();

        $this->actingAs($this->salesperson)
            ->get(route('deliveries.signature', $this->delivery))
            ->assertOk();
    });

    it('refuse de terminer sans signature', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->call('startConfirmation')
            ->call('confirmReceipt')
            ->call('complete', '')
            ->assertHasErrors('signature');

        expect($this->delivery->fresh()->status)->toBe(DeliveryStatus::InTransit)
            ->and(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('ferme la livraison « Non livrée » quand le client est absent, et avise le vendeur', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->call('markUndelivered', 'customer_absent')
            ->assertHasNoErrors();

        expect($this->delivery->fresh())
            ->status->toBe(DeliveryStatus::Undelivered)
            ->undelivered_reason->toBe(UndeliveredReason::CustomerAbsent)
            ->and($this->pair->fresh())
            ->status->toBe(CustomerOrderLineStatus::InStock)
            ->quantity_reserved->toBe(2)
            ->and($this->singleLine->fresh()->refusal_reason)->toBe('Client absent')
            ->and($this->salesperson->notifications()->sole()->data['title'])->toBe('Livraison non livrée (client absent) — commande #'.$this->order->id)
            ->and($this->order->pickableQuantities())->toBe([$this->pair->id => 2, $this->single->id => 1]);

        expectInventoryIntegrity($this->pair->product);
    });

    it('ferme la livraison « Non livrée » quand le client refuse tout, avec une raison par article', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->set('completionStep', 'refused')
            ->set("refusalReasons.{$this->pairLine->id}", 'Mauvaise couleur')
            ->call('markUndelivered', 'all_refused')
            ->assertHasErrors('refused')
            ->set("refusalReasons.{$this->singleLine->id}", 'Plus besoin')
            ->call('markUndelivered', 'all_refused')
            ->assertHasNoErrors();

        expect($this->delivery->fresh())
            ->status->toBe(DeliveryStatus::Undelivered)
            ->undelivered_reason->toBe(UndeliveredReason::AllRefused)
            ->and($this->pairLine->fresh()->refusal_reason)->toBe('Mauvaise couleur');
    });

    it('supprime l\'image après 365 jours et garde le nom du signataire', function () {
        app(CompleteDelivery::class)->handle($this->delivery, [$this->pairLine->id => 2, $this->singleLine->id => 1], [], 'Marie Tremblay', signaturePng());
        $path = $this->delivery->fresh()->signature_path;

        $this->travel(364)->days();
        $this->artisan('deliveries:purge-signatures')->assertSuccessful();
        expect(Storage::disk('local')->exists($path))->toBeTrue();

        $this->travel(2)->days();
        $this->artisan('deliveries:purge-signatures')->assertSuccessful();

        expect(Storage::disk('local')->exists($path))->toBeFalse()
            ->and($this->delivery->fresh())
            ->signature_path->toBeNull()
            ->signed_by_name->toBe('Marie Tremblay')
            ->signed_at->not->toBeNull();
    });
});

describe('paiement à la livraison (#93)', function () {
    beforeEach(function () {
        $this->line = journaledLine($this->order, 1, 1);
        $this->delivery = plannedDelivery($this->order, [$this->line->id => 1], attributes: ['pay_on_delivery' => true]);
        $this->order->loadDelivery($this->delivery, [$this->delivery->lines()->sole()->id => 1]);
        $this->terminal = CustomerPaymentMethod::factory()->create(['name' => 'TPV', 'is_usable_on_delivery' => true]);
    });

    it('n\'offre que les modes utilisables à la livraison, puis débloque la confirmation', function () {
        Livewire::test(Mine::class)
            ->call('open', $this->delivery->id)
            ->assertSee('Paiement à percevoir : 114.98 $')
            ->assertSet('payment.method_id', $this->terminal->id)
            ->set('payment.method_id', $this->cash->id)
            ->call('collectPayment')
            ->assertHasErrors('payment.method_id')
            ->set('payment.method_id', $this->terminal->id)
            ->call('collectPayment')
            ->assertHasNoErrors()
            ->assertDontSee('Paiement à percevoir');

        expect($this->order->payments()->sole())
            ->customer_payment_method_id->toBe($this->terminal->id)
            ->amount->toBeMoney(11498);
    });

    it('marque un mode de paiement « utilisable à la livraison » dans la gestion des modes de paiement', function () {
        $admin = User::factory()->withRole('admin')->create();
        $method = CustomerPaymentMethod::factory()->create(['name' => 'Débit']);

        Livewire::actingAs($admin)
            ->test(PaymentMethods::class)
            ->call('openEdit', $method->id)
            ->set('isUsableOnDelivery', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($method->fresh()->is_usable_on_delivery)->toBeTrue();
    });
});

it('refuse l\'image de la signature sans la permission de voir les commandes', function () {
    $delivery = CustomerOrderDelivery::factory()->create(['customer_order_id' => $this->order->id, 'signature_path' => 'signatures/x.png', 'signed_at' => now()]);
    Storage::disk('local')->put('signatures/x.png', 'png');

    $this->actingAs(User::factory()->withRole('warehouse')->create())
        ->get(route('deliveries.signature', $delivery))
        ->assertForbidden();
});
