<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Livewire\Catalog\Services;
use App\Livewire\CustomerOrders\Show;
use App\Livewire\Products\Show as ProductShow;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Livewire\Livewire;

/**
 * Service externe offert par un fournisseur de services au coût et au prix donnés.
 *
 * @return array{0: Service, 1: Supplier}
 */
function externalService(float $cost = 60, float $price = 100, array $attributes = []): array
{
    $supplier = Supplier::factory()->create(['type' => SupplierType::Service]);
    $service = Service::factory()->create($attributes);
    $service->suppliers()->attach($supplier->id, ['cost' => $cost, 'selling_price' => $price]);

    return [$service, $supplier];
}

describe('catalogue de services', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->withRole('admin')->create());
    });

    it('crée un service interne non taxable avec son prix', function () {
        Livewire::test(Services::class)
            ->call('openCreate')
            ->set('name', 'Livraison locale')
            ->set('descriptionTemplate', 'Livraison de {produit}')
            ->set('isInternal', true)
            ->set('sellingPrice', '79,95')
            ->set('isTaxable', false)
            ->call('save')
            ->assertHasNoErrors();

        expect(Service::sole())
            ->is_internal->toBeTrue()
            ->selling_price->toBe(79.95)
            ->is_taxable->toBeFalse()
            ->and(Service::sole()->suppliers()->count())->toBe(0);
    });

    it('crée un service externe offert par plusieurs fournisseurs à des prix différents', function () {
        $installer = Supplier::factory()->create(['type' => SupplierType::Service]);
        $carrier = Supplier::factory()->create(['type' => SupplierType::Shipping]);

        Livewire::test(Services::class)
            ->call('openCreate')
            ->set('name', 'Installation')
            ->set('offers', [
                ['supplier_id' => $installer->id, 'cost' => '60', 'selling_price' => '100'],
                ['supplier_id' => $carrier->id, 'cost' => '45', 'selling_price' => '90'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $service = Service::sole();

        expect($service->pricingFor($installer->id))->toBe(['selling_price' => 100.0, 'cost' => 60.0])
            ->and($service->pricingFor($carrier->id))->toBe(['selling_price' => 90.0, 'cost' => 45.0]);

        Livewire::test(Services::class)
            ->call('openEdit', $service->id)
            ->assertCount('offers', 2)
            ->call('removeOffer', 1)
            ->call('save')
            ->assertHasNoErrors();

        expect($service->suppliers()->pluck('suppliers.id')->all())->toBe([$installer->id]);
    });

    it('exige un prix pour un service interne et un fournisseur pour un service externe', function () {
        Livewire::test(Services::class)
            ->call('openCreate')
            ->set('name', 'Soudure')
            ->set('offers', [])
            ->call('save')
            ->assertHasErrors('offers')
            ->set('isInternal', true)
            ->call('save')
            ->assertHasErrors('sellingPrice');
    });

    it('refuse un fournisseur de produits dans les offres', function () {
        $productSupplier = Supplier::factory()->create(['type' => SupplierType::Product]);

        Livewire::test(Services::class)
            ->call('openCreate')
            ->set('name', 'Soudure')
            ->set('offers', [['supplier_id' => $productSupplier->id, 'cost' => '1', 'selling_price' => '2']])
            ->call('save')
            ->assertHasErrors('offers.0.supplier_id');
    });

    it('remplace {produit} dans la description modèle', function () {
        $service = Service::factory()->create(['name' => 'Installation', 'description_template' => 'Installation de {produit}, raccordement inclus']);

        expect($service->describe('Lave-vaisselle XL'))->toBe('Installation de Lave-vaisselle XL, raccordement inclus')
            ->and(Service::factory()->create(['name' => 'Soudure'])->describe())->toBe('Soudure');
    });
});

it('rend un produit non taxable depuis sa fiche', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $product = Product::factory()->create(['model' => 'ABC-1', 'clean_model' => 'ABC1', 'supplier_model' => 'ABC-1'])->fresh();

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSet('isTaxable', true)
        ->set('isTaxable', false)
        ->call('saveGeneral')
        ->assertHasNoErrors();

    expect($product->fresh()->is_taxable)->toBeFalse();
});

describe('services dans la commande client', function () {
    beforeEach(function () {
        Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
        $this->user = User::factory()->withRole('visiteur')->create();
        $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
        $this->actingAs($this->user);
        $this->order = CustomerOrder::factory()->create();
    });

    it('vend un service interne « À faire » au prix du service, puis le complète', function () {
        $service = Service::factory()->internal(80)->create(['name' => 'Livraison']);

        $line = $this->order->addService($service, null, 1, 'Livraison 2e étage');

        expect($line)
            ->status->toBe(CustomerOrderLineStatus::ToDo)
            ->supplier_id->toBeNull()
            ->supplier_order_line_id->toBeNull()
            ->unit_price->toBe(80.0)
            ->description->toBe('Livraison 2e étage')
            ->and(SupplierOrder::count())->toBe(0)
            ->and($this->order->fresh()->subtotal)->toBe(80.0);

        expect($this->order->amountRequiredFor([]))->toBe(round($this->order->fresh()->total * 0.3, 2));

        $this->order->completeServiceLine($line);

        expect($line->fresh())->status->toBe(CustomerOrderLineStatus::Completed)->delivered_at->not->toBeNull()
            ->and($this->order->fresh()->amountRequiredFor([]))->toBe($this->order->fresh()->total);
    });

    it('commande un service externe au fournisseur choisi, puis le complète avec la commande de services', function () {
        [$service, $supplier] = externalService(cost: 60, price: 100, attributes: ['name' => 'Installation']);

        $line = $this->order->addService($service, $supplier->id, 2, 'Installation lave-vaisselle');

        $supplierLine = $line->fresh()->supplierOrderLine;

        expect($line->fresh())
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->quantity_on_order->toBe(2)
            ->unit_price->toBe(100.0)
            ->and($supplierLine)
            ->product_id->toBeNull()
            ->quantity->toBe(2)
            ->unit_cost->toBe(60.0)
            ->description->toContain('Installation lave-vaisselle')
            ->and($supplierLine->order)
            ->type->toBe(SupplierType::Service)
            ->supplier_id->toBe($supplier->id);

        $supplierOrder = $supplierLine->order;
        $supplierOrder->send();
        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Ordered);

        $supplierOrder->fresh()->complete();

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Completed)
            ->and($supplierOrder->fresh()->status)->toBe(SupplierOrderStatus::Received);
    });

    it('refuse un fournisseur qui n\'offre pas le service', function () {
        [$service] = externalService();
        $other = Supplier::factory()->create(['type' => SupplierType::Service]);

        expect(fn () => $this->order->addService($service, $other->id, 1, 'Installation'))->toThrow(DomainException::class)
            ->and(fn () => $this->order->addService($service, null, 1, 'Installation'))->toThrow(DomainException::class);
    });

    it('ne taxe pas les lignes non taxables', function () {
        $service = Service::factory()->internal(50)->create(['is_taxable' => false]);
        $this->order->addService($service, null, 1, 'Frais');
        stockedLine($this->order, 1, 1, 100);

        $order = $this->order->fresh();
        $taxed = $order->taxesFor(100)['total'];

        expect($order->subtotal)->toBe(150.0)
            ->and($order->total)->toBe(round($taxed + 50, 2))
            ->and($order->taxLines->sum('amount'))->toEqual(round($taxed - 100, 2));
    });

    it('fige la taxabilité du produit sur la ligne', function () {
        $product = Product::factory()->create(['is_taxable' => false]);

        $line = $this->order->addProduct($product);

        expect($line->is_taxable)->toBeFalse();
    });

    it('modifie un service non commandé et la ligne fournisseur suit', function () {
        [$service, $supplier] = externalService();
        $line = $this->order->addService($service, $supplier->id, 1, 'Installation');

        $this->order->updateServiceLine($line, 3, 120, 'Installation au sous-sol', 'Appeler avant');

        expect($line->fresh())->quantity->toBe(3)->unit_price->toBe(120.0)->description->toBe('Installation au sous-sol')->note->toBe('Appeler avant')
            ->and(SupplierOrderLine::sole()->quantity)->toBe(3);
    });

    it('refuse de modifier un service déjà commandé, sauf la note', function () {
        [$service, $supplier] = externalService();
        $line = $this->order->addService($service, $supplier->id, 1, 'Installation');
        SupplierOrder::sole()->send();

        expect(fn () => $this->order->updateServiceLine($line->fresh(), 2, 100, 'Installation', null))->toThrow(DomainException::class);

        $this->order->updateServiceLine($line->fresh(), 1, 100, 'Installation', 'Note ajoutée');

        expect($line->fresh()->note)->toBe('Note ajoutée');
    });

    it('retire un service non commandé et sa ligne fournisseur', function () {
        [$service, $supplier] = externalService();
        $line = $this->order->addService($service, $supplier->id, 1, 'Installation');

        $this->order->removeLine($line);

        expect($this->order->lines()->count())->toBe(0)->and(SupplierOrderLine::count())->toBe(0);
    });

    it('ne permet pas de retourner ni de déclarer défectueux un service', function () {
        $service = Service::factory()->internal()->create();
        $line = $this->order->addService($service, null, 1, 'Main-d\'œuvre');
        $this->order->completeServiceLine($line);

        expect(fn () => $this->order->returnLine($line->fresh(), 1, refund: false))->toThrow(DomainException::class)
            ->and(fn () => $this->order->returnDefective($line->fresh(), 1, 'x', replace: true))->toThrow(DomainException::class);
    });

    it('passe la commande à « Ramassée » quand les produits sont ramassés et les services complétés', function () {
        $service = Service::factory()->internal(20)->create();
        $serviceLine = $this->order->addService($service, null, 1, 'Assemblage');
        $productLine = stockedLine($this->order, 1, 1, 100);
        $this->order->completeServiceLine($serviceLine);

        $this->order->pickUp([$productLine->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $this->order->fresh()->balance_due]]);

        expect($this->order->fresh()->status)->toBe(CustomerOrderStatus::PickedUp);
    });

    it('ajoute un service depuis le modal : fournisseur présélectionné, prix et description proposés', function () {
        [$service, $supplier] = externalService(price: 100, attributes: ['name' => 'Installation', 'description_template' => 'Installation de {produit}']);
        $productLine = stockedLine($this->order, 1, 1, 500);

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertSee('Ajouter un service')
            ->call('openServiceModal')
            ->set('serviceId', (string) $service->id)
            ->assertSet('serviceSupplierId', (string) $supplier->id)
            ->assertSet('servicePrice', '100.00')
            ->assertSet('serviceDescription', 'Installation de')
            ->set('serviceProductLineId', (string) $productLine->id)
            ->assertSet('serviceDescription', 'Installation de '.$productLine->product->model)
            ->set('serviceDescription', 'Installation de '.$productLine->product->model.', 2e étage')
            ->call('addService')
            ->assertHasNoErrors()
            ->assertSet('showServiceModal', false);

        expect($this->order->lines()->whereNotNull('service_id')->sole())
            ->supplier_id->toBe($supplier->id)
            ->description->toEndWith('2e étage');
    });

    it('modifie et complète un service depuis le modal de la ligne', function () {
        $service = Service::factory()->internal(40)->create();
        $line = $this->order->addService($service, null, 1, 'Assemblage');

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSet('editDescription', 'Assemblage')
            ->set('editQuantity', '2')
            ->call('saveLine')
            ->assertHasNoErrors()
            ->call('openLineModal', $line->id)
            ->assertSee('Marquer complété')
            ->assertDontSeeHtml('openReturnModal')
            ->call('completeService', $line->id);

        expect($line->fresh())->quantity->toBe(2)->status->toBe(CustomerOrderLineStatus::Completed);
    });
});
