<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\CustomerOrderStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierOrderStatus;
use App\Livewire\CustomerOrders\Index;
use App\Livewire\CustomerOrders\Show;
use App\Livewire\Orders\Show as SupplierOrderShow;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\Role;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.create', 'customer_orders.edit', 'customers.view', 'customers.create']);
    $this->actingAs($this->user);
});

describe('index', function () {
    it('liste les commandes et cherche par numéro ou par client', function () {
        $tremblay = CustomerOrder::factory()->for(customer::factory()->state(['firstname' => 'Marguerite', 'lastname' => 'Tremblay']))->create();
        $gagnon = CustomerOrder::factory()->for(customer::factory()->state(['firstname' => 'Paul', 'lastname' => 'Gagnon']))->create();

        Livewire::test(Index::class)
            ->assertSee('Tremblay')
            ->assertSee('Gagnon')
            ->set('search', 'Tremb')
            ->assertSee('Tremblay')
            ->assertDontSee('Gagnon')
            ->set('search', (string) $gagnon->id)
            ->assertSee('Gagnon');

        expect($tremblay->id)->not->toBe($gagnon->id);
    });

    it('filtre les commandes par statut', function () {
        CustomerOrder::factory()->for(customer::factory()->state(['lastname' => 'Bouchard']))->create();
        CustomerOrder::factory()->status(CustomerOrderStatus::Delivered)->for(customer::factory()->state(['lastname' => 'Pelletier']))->create();

        Livewire::test(Index::class)
            ->set('statusFilter', CustomerOrderStatus::Delivered->value)
            ->assertSee('Pelletier')
            ->assertDontSee('Bouchard');
    });

    it('crée une commande pour un client trouvé avec l\'utilisateur comme vendeur à 100 %', function () {
        $customer = customer::factory()->create(['firstname' => 'Marguerite', 'lastname' => 'Tremblay']);

        $component = Livewire::test(Index::class)
            ->call('openCreate')
            ->set('customerSearch', 'Margu')
            ->assertSee('Tremblay')
            ->call('create', $customer->id);

        $order = CustomerOrder::sole();

        $component->assertRedirect(route('customer-orders.show', $order));

        expect($order->customer_id)->toBe($customer->id)
            ->and($order->status)->toBe(CustomerOrderStatus::New)
            ->and($order->created_by)->toBe($this->user->id)
            ->and($order->salespeople->pluck('pivot.percent', 'id')->all())->toBe([$this->user->id => 100]);
    });

    it('crée la commande pour le client créé via le formulaire client', function () {
        $customer = customer::factory()->create();

        Livewire::test(Index::class)
            ->call('openCreate')
            ->dispatch('customer-saved', id: $customer->id);

        expect(CustomerOrder::sole()->customer_id)->toBe($customer->id);
    });

    it('refuse la création sans permission', function () {
        $this->user->revokePermissionTo('customer_orders.create');

        Livewire::test(Index::class)
            ->assertDontSeeHtml('wire:click="openCreate"')
            ->call('create', customer::factory()->create()->id)
            ->assertForbidden();

        expect(CustomerOrder::count())->toBe(0);
    });
});

describe('show', function () {
    beforeEach(function () {
        $this->order = CustomerOrder::factory()->create();
        $this->order->syncSalespeople([['user_id' => $this->user->id, 'percent' => 100]]);
    });

    it('affiche la commande', function () {
        $this->get(route('customer-orders.show', $this->order))
            ->assertOk()
            ->assertSee($this->order->customer->lastname);
    });

    it('change le client de la commande', function () {
        $customer = customer::factory()->create();

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCustomerModal')
            ->call('selectCustomer', $customer->id)
            ->assertSet('showCustomerModal', false);

        expect($this->order->fresh()->customer_id)->toBe($customer->id);
    });

    it('cherche un produit par ID sans fournisseur', function () {
        $product = Product::factory()->create();
        Product::factory()->create(['model' => 'AUTRE']);

        $component = Livewire::test(Show::class, ['order' => $this->order])->set('productSearch', (string) $product->id);

        expect($component->instance()->getProductResults()->pluck('id')->all())->toBe([$product->id]);

        $component->set('productSearch', 'AUTRE');
        expect($component->instance()->getProductResults())->toBeEmpty();
    });

    it('cherche un produit par modèle dans un fournisseur', function () {
        $product = Product::factory()->create(['model' => 'SOFA-100']);
        Product::factory()->create(['model' => 'SOFA-200']);

        $component = Livewire::test(Show::class, ['order' => $this->order])
            ->set('productSupplierId', (string) $product->supplier_id)
            ->set('productSearch', 'SOFA');

        expect($component->instance()->getProductResults()->pluck('id')->all())->toBe([$product->id]);
    });

    it('réserve le stock dès l\'ajout du produit, au prix vendant, et met à jour le solde', function () {
        $product = Product::factory()->create(['cost' => 50]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 5, 'quantity_reserved' => 0]);

        Livewire::test(Show::class, ['order' => $this->order])->call('addProduct', $product->id);

        $line = $this->order->lines()->sole();
        $stock = $product->inventoryStock()->sole();

        expect($line->status)->toBe(CustomerOrderLineStatus::InStock)
            ->and($line->quantity)->toBe(1)
            ->and($line->quantity_reserved)->toBe(1)
            ->and($line->quantity_on_order)->toBe(0)
            ->and($line->unit_price)->toBe($product->selling_price)
            ->and($stock->quantity_in_stock)->toBe(4)
            ->and($stock->quantity_reserved)->toBe(1)
            ->and($this->order->fresh()->balance_due)->toBe($product->selling_price);

        expect(InventoryMovement::sole())
            ->type->toBe(InventoryMovementType::CustomerReservation)
            ->from_status->toBe(InventoryStatus::InStock)
            ->to_status->toBe(InventoryStatus::ReservedCustomer)
            ->reference_id->toBe($this->order->id);
    });

    it('prend d\'abord le stock disponible puis met le reste en commande', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id);

        $line = $this->order->lines()->sole();

        expect($line->quantity)->toBe(2)
            ->and($line->quantity_reserved)->toBe(1)
            ->and($line->quantity_on_order)->toBe(1)
            ->and($line->status)->toBe(CustomerOrderLineStatus::OnOrder)
            ->and($product->inventoryStock()->sole()->quantity_in_stock)->toBe(0);
    });

    it('met tout en commande sans stock', function () {
        $product = Product::factory()->create();

        Livewire::test(Show::class, ['order' => $this->order])->call('addProduct', $product->id);

        expect($this->order->lines()->sole())
            ->quantity_reserved->toBe(0)
            ->quantity_on_order->toBe(1)
            ->status->toBe(CustomerOrderLineStatus::OnOrder);
    });

    it('affiche les quantités sans permettre de les modifier dans le tableau', function () {
        $line = $this->order->addProduct(Product::factory()->create());

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertSee('Qté totale')
            ->assertDontSeeHtml('wire:model.blur')
            ->assertSeeHtml('$wire.openLineModal('.$line->id.')');
    });

    it('ouvre le modal d\'édition avec les valeurs de la ligne', function () {
        $line = $this->order->addProduct(Product::factory()->create());
        $line->update(['note' => 'Livrer au sous-sol']);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->assertSet('showLineModal', true)
            ->assertSet('editReserved', '0')
            ->assertSet('editOnOrder', '1')
            ->assertSet('editUnitPrice', number_format($line->unit_price, 2, '.', ''))
            ->assertSet('editNote', 'Livrer au sous-sol');
    });

    it('modifie le prix vendant et la note et recalcule le solde', function () {
        $line = $this->order->addProduct(Product::factory()->create());

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editUnitPrice', '199,95')
            ->set('editNote', 'Couleur à confirmer')
            ->call('saveLine')
            ->assertHasNoErrors()
            ->assertSet('showLineModal', false);

        expect($line->fresh())->unit_price->toBe(199.95)->note->toBe('Couleur à confirmer')
            ->and($this->order->fresh()->balance_due)->toBe(199.95);
    });

    it('libère le stock quand on réduit la réservation', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 3, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product, 3);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editReserved', '1')
            ->call('saveLine')
            ->assertHasNoErrors();

        expect($line->fresh())
            ->quantity_reserved->toBe(1)
            ->quantity_on_order->toBe(0)
            ->quantity->toBe(1)
            ->and($product->inventoryStock()->sole())
            ->quantity_in_stock->toBe(2)
            ->quantity_reserved->toBe(1);
    });

    it('déplace des unités réservées vers la commande et inversement si le stock est encore disponible', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 2, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product, 2);

        $component = Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editReserved', '1')
            ->set('editOnOrder', '1')
            ->call('saveLine')
            ->assertHasNoErrors();

        expect($line->fresh())->quantity_reserved->toBe(1)->quantity_on_order->toBe(1)
            ->and($product->inventoryStock()->sole()->quantity_in_stock)->toBe(1);

        $component->call('openLineModal', $line->id)
            ->set('editReserved', '2')
            ->set('editOnOrder', '0')
            ->call('saveLine')
            ->assertHasNoErrors();

        expect($line->fresh())->quantity_reserved->toBe(2)->quantity_on_order->toBe(0)
            ->status->toBe(CustomerOrderLineStatus::InStock)
            ->and($product->inventoryStock()->sole()->quantity_in_stock)->toBe(0);
    });

    it('refuse d\'augmenter la réservation quand le stock libéré a été pris par un autre client', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product);

        $component = Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editReserved', '0')
            ->set('editOnOrder', '1')
            ->call('saveLine')
            ->assertHasNoErrors();

        CustomerOrder::factory()->create()->addProduct($product);

        $component->call('openLineModal', $line->id)
            ->set('editReserved', '1')
            ->set('editOnOrder', '0')
            ->set('editNote', 'Ne doit pas être gardée')
            ->call('saveLine')
            ->assertHasErrors('editReserved')
            ->assertSet('showLineModal', true);

        expect($line->fresh())->quantity_reserved->toBe(0)->note->toBeNull()
            ->and($product->inventoryStock()->sole()->quantity_reserved)->toBe(1);
    });

    it('refuse une ligne à quantité nulle', function () {
        $line = $this->order->addProduct(Product::factory()->create());

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editOnOrder', '0')
            ->call('saveLine')
            ->assertHasErrors('editReserved');

        expect($line->fresh()->quantity)->toBe(1);
    });

    it('permet seulement la note sur une ligne livrée', function () {
        $line = CustomerOrderLine::factory()->for($this->order, 'order')->status(CustomerOrderLineStatus::Delivered)
            ->create(['quantity' => 1, 'quantity_reserved' => 1, 'unit_price' => 100]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openLineModal', $line->id)
            ->set('editNote', 'Client satisfait')
            ->call('saveLine')
            ->assertHasNoErrors()
            ->call('openLineModal', $line->id)
            ->set('editUnitPrice', '50')
            ->call('saveLine')
            ->assertHasErrors('editReserved');

        expect($line->fresh())->note->toBe('Client satisfait')->unit_price->toBe(100.0);
    });

    it('retire une ligne, libère son stock et recalcule le solde', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product);

        Livewire::test(Show::class, ['order' => $this->order])->call('removeLine', $line->id);

        expect($this->order->lines()->count())->toBe(0)
            ->and($this->order->fresh()->balance_due)->toBe(0.0)
            ->and($product->inventoryStock()->sole())
            ->quantity_in_stock->toBe(1)
            ->quantity_reserved->toBe(0);
    });

    it('ne retire pas une ligne livrée', function () {
        $line = CustomerOrderLine::factory()->for($this->order, 'order')->status(CustomerOrderLineStatus::Delivered)->create();

        Livewire::test(Show::class, ['order' => $this->order])->call('removeLine', $line->id);

        expect($line->fresh())->not->toBeNull();
    });

    it('ajoute le produit rattaché à un UPC scanné', function () {
        $product = Product::factory()->create();
        $product->upcs()->create(['upc' => '012345678905']);

        Livewire::test(Show::class, ['order' => $this->order])
            ->set('upcScan', '012345678905')
            ->call('scanUpc')
            ->assertSet('upcScan', '');

        expect($this->order->lines()->sole()->product_id)->toBe($product->id);
    });

    it('signale un UPC inconnu', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->set('upcScan', '999')
            ->call('scanUpc')
            ->assertHasErrors('upcScan');

        expect($this->order->lines()->count())->toBe(0);
    });

    it('crée le produit depuis la liste de prix du fournisseur et l\'ajoute', function () {
        $this->user->givePermissionTo('products.create');
        $priceList = PriceList::factory()->create(['starts_on' => today()->subDay(), 'ends_on' => today()->addMonth()]);
        $list = PriceListList::factory()->create(['price_list_id' => $priceList->id]);
        $item = PriceListItem::factory()->create(['price_list_list_id' => $list->id, 'model' => 'TABLE-9', 'clean_model' => 'TABLE9']);

        $component = Livewire::test(Show::class, ['order' => $this->order])
            ->set('productSupplierId', (string) $priceList->supplier_id)
            ->set('priceListSearch', 'TABLE');

        expect($component->instance()->getPriceListResults()->pluck('id')->all())->toBe([$item->id]);

        $component->call('addFromPriceList', $item->id);

        $product = Product::where('supplier_id', $priceList->supplier_id)->where('model', 'TABLE-9')->firstOrFail();
        expect($this->order->lines()->sole()->product_id)->toBe($product->id);
    });

    it('refuse la création depuis la liste de prix sans permission', function () {
        $priceList = PriceList::factory()->create(['starts_on' => today()->subDay(), 'ends_on' => today()->addMonth()]);
        $list = PriceListList::factory()->create(['price_list_id' => $priceList->id]);
        $item = PriceListItem::factory()->create(['price_list_list_id' => $list->id]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->set('productSupplierId', (string) $priceList->supplier_id)
            ->call('addFromPriceList', $item->id)
            ->assertForbidden();
    });

    it('refuse les modifications sans permission', function () {
        $this->user->revokePermissionTo('customer_orders.edit');

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertDontSee('Ajouter un produit')
            ->call('addProduct', Product::factory()->create()->id)
            ->assertForbidden();
    });

    it('cache le bouton vendeur et refuse la modification sans permission', function () {
        Livewire::test(Show::class, ['order' => $this->order])
            ->assertDontSee('Ajouter un vendeur')
            ->call('openSalespeopleModal')
            ->assertForbidden();
    });

    describe('vendeurs', function () {
        beforeEach(function () {
            $this->user->givePermissionTo('customer_orders.assign_salespeople');
            $this->other = User::factory()->withRole('visiteur')->create();
            $this->third = User::factory()->withRole('visiteur')->create();
        });

        it('ouvre le modal avec les vendeurs de la commande', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->assertSee('Ajouter un vendeur')
                ->call('openSalespeopleModal')
                ->assertSet('salespeopleDraft', [['user_id' => $this->user->id, 'percent' => 100]]);
        });

        it('répartit la vente entre deux vendeurs', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->set('salespeopleDraft', [
                    ['user_id' => $this->user->id, 'percent' => 75],
                    ['user_id' => $this->other->id, 'percent' => 25],
                ])
                ->call('saveSalespeople')
                ->assertHasNoErrors();

            expect($this->order->salespeople()->get()->pluck('pivot.percent', 'id')->all())
                ->toBe([$this->user->id => 75, $this->other->id => 25]);
        });

        it('ajoute 1 au premier vendeur quand la somme est de 99', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->set('salespeopleDraft', [
                    ['user_id' => $this->user->id, 'percent' => 33],
                    ['user_id' => $this->other->id, 'percent' => 33],
                    ['user_id' => $this->third->id, 'percent' => 33],
                ])
                ->call('saveSalespeople')
                ->assertHasNoErrors();

            expect($this->order->salespeople()->get()->pluck('pivot.percent', 'id')->all())
                ->toBe([$this->user->id => 34, $this->other->id => 33, $this->third->id => 33]);
        });

        it('refuse une somme différente de 100', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->set('salespeopleDraft', [
                    ['user_id' => $this->user->id, 'percent' => 50],
                    ['user_id' => $this->other->id, 'percent' => 25],
                ])
                ->call('saveSalespeople')
                ->assertHasErrors('salespeopleDraft');

            expect($this->order->salespeople()->get()->pluck('pivot.percent', 'id')->all())->toBe([$this->user->id => 100]);
        });

        it('refuse le même vendeur deux fois', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->set('salespeopleDraft', [
                    ['user_id' => $this->user->id, 'percent' => 50],
                    ['user_id' => $this->user->id, 'percent' => 50],
                ])
                ->call('saveSalespeople')
                ->assertHasErrors('salespeopleDraft.1.user_id');
        });

        it('désactive dans les autres lignes un vendeur déjà choisi', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->call('addSalesperson')
                ->set('salespeopleDraft.1.user_id', $this->other->id)
                ->assertSeeHtmlInOrder(['value="'.$this->other->id.'"', 'disabled']);
        });

        it('limite à 3 vendeurs', function () {
            Livewire::test(Show::class, ['order' => $this->order])
                ->call('openSalespeopleModal')
                ->call('addSalesperson')
                ->call('addSalesperson')
                ->call('addSalesperson')
                ->assertCount('salespeopleDraft', 3);
        });
    });
});

it('rattache des lignes à une commande client', function () {
    $order = CustomerOrder::factory()->create();
    $line = CustomerOrderLine::factory()->for($order, 'order')->create(['quantity' => 2, 'unit_price' => 149.99, 'note' => 'Livrer au sous-sol']);

    expect($order->lines->pluck('id')->all())->toBe([$line->id])
        ->and($line->fresh()->status)->toBe(CustomerOrderLineStatus::InStock)
        ->and($line->total)->toBe(299.98)
        ->and($line->delivered_at)->toBeNull()
        ->and($line->returned_at)->toBeNull();
});

describe('lien avec les commandes fournisseurs', function () {
    beforeEach(function () {
        $this->order = CustomerOrder::factory()->create();
    });

    it('met la quantité à commander sur le brouillon du fournisseur, créé au besoin', function () {
        $product = Product::factory()->create(['cost' => 40]);

        $line = $this->order->addProduct($product);
        $this->order->addProduct($product);

        $supplierOrder = SupplierOrder::sole();
        $supplierLine = $supplierOrder->lines()->sole();

        expect($supplierOrder)->supplier_id->toBe($product->supplier_id)->status->toBe(SupplierOrderStatus::Draft)
            ->and($supplierLine)->product_id->toBe($product->id)->quantity->toBe(2)->unit_cost->toBe(40.0)
            ->and($line->fresh()->supplier_order_line_id)->toBe($supplierLine->id);
    });

    it('utilise le brouillon existant du fournisseur', function () {
        $product = Product::factory()->create();
        $draft = SupplierOrder::factory()->create(['supplier_id' => $product->supplier_id]);

        $this->order->addProduct($product);

        expect(SupplierOrder::count())->toBe(1)
            ->and($draft->lines()->sole()->product_id)->toBe($product->id);
    });

    it('ne commande rien quand le stock couvre la quantité', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 2, 'quantity_reserved' => 0]);

        $this->order->addProduct($product, 2);

        expect(SupplierOrderLine::count())->toBe(0);
    });

    it('ajuste puis supprime la ligne fournisseur quand la quantité en commande change', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product, 3);

        expect(SupplierOrderLine::sole()->quantity)->toBe(2);

        $this->order->adjustLine($line, 1, 1);
        expect(SupplierOrderLine::sole()->quantity)->toBe(1);

        $this->order->adjustLine($line, 1, 0);
        expect(SupplierOrderLine::count())->toBe(0)
            ->and($line->fresh()->supplier_order_line_id)->toBeNull();
    });

    it('retire la ligne client, sa ligne fournisseur et libère son stock', function () {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product, 2);

        Livewire::test(Show::class, ['order' => $this->order])->call('removeLine', $line->id);

        expect($this->order->lines()->count())->toBe(0)
            ->and(SupplierOrderLine::count())->toBe(0)
            ->and($product->inventoryStock()->sole()->quantity_in_stock)->toBe(1);
    });

    it('passe la ligne client à « Commandé » à l\'envoi et ne permet plus de la retirer', function () {
        $product = Product::factory()->create();
        $line = $this->order->addProduct($product);

        SupplierOrder::sole()->send();

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Ordered);

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertDontSeeHtml('removeLine('.$line->id.')')
            ->call('removeLine', $line->id);

        expect($line->fresh())->not->toBeNull()
            ->and(SupplierOrderLine::count())->toBe(1);
    });

    it('refuse de commander un produit non commandable', function () {
        $product = Product::factory()->create(['is_non_orderable' => true]);

        Livewire::test(Show::class, ['order' => $this->order])->call('addProduct', $product->id);

        expect($this->order->lines()->count())->toBe(0)
            ->and(SupplierOrder::count())->toBe(0);
    });

    it('refuse de supprimer de la commande fournisseur une ligne liée à un client', function () {
        $this->user->givePermissionTo(['supplier_orders.view', 'supplier_orders.edit']);
        $this->order->addProduct(Product::factory()->create());
        $supplierLine = SupplierOrderLine::sole();

        Livewire::test(SupplierOrderShow::class, ['order' => $supplierLine->order])
            ->assertSee(__('commande #:id', ['id' => $this->order->id]))
            ->call('removeLine', $supplierLine->id);

        expect($supplierLine->fresh())->not->toBeNull();
    });

    it('répercute sur la ligne client la quantité modifiée dans la commande fournisseur', function () {
        $this->user->givePermissionTo(['supplier_orders.view', 'supplier_orders.edit']);
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
        $line = $this->order->addProduct($product, 2);
        $supplierLine = SupplierOrderLine::sole();

        Livewire::test(SupplierOrderShow::class, ['order' => $supplierLine->order])
            ->call('startEditLine', $supplierLine->id)
            ->set('editQuantity', '3')
            ->call('saveLine');

        expect($line->fresh())
            ->quantity_reserved->toBe(1)
            ->quantity_on_order->toBe(3)
            ->quantity->toBe(4)
            ->status->toBe(CustomerOrderLineStatus::OnOrder)
            ->and($this->order->fresh()->balance_due)->toBe(round(4 * $line->unit_price, 2));
    });

    it('répercute aussi la quantité modifiée après l\'envoi sans changer le statut « Commandé »', function () {
        $line = $this->order->addProduct(Product::factory()->create(), 3);
        $supplierOrder = SupplierOrder::sole();
        $supplierOrder->send();

        $supplierOrder->updateLine(SupplierOrderLine::sole(), 2, SupplierOrderLine::sole()->unit_cost);

        expect($line->fresh())
            ->quantity_on_order->toBe(2)
            ->quantity->toBe(2)
            ->status->toBe(CustomerOrderLineStatus::Ordered);
    });

    describe('substitution fournisseur', function () {
        beforeEach(function () {
            $this->product = Product::factory()->create(['model' => 'SOFA-A']);
            $this->substitute = Product::factory()->create(['model' => 'SOFA-B', 'supplier_id' => $this->product->supplier_id]);
        });

        it('change le produit de la ligne client quand rien n\'est réservé ni reçu, au même prix', function () {
            $line = $this->order->addProduct($this->product, 2);
            $price = $line->unit_price;
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();

            $replacement = $supplierOrder->substituteLine(SupplierOrderLine::sole(), $this->substitute);

            expect($line->fresh())
                ->product_id->toBe($this->substitute->id)
                ->supplier_order_line_id->toBe($replacement->id)
                ->quantity_on_order->toBe(2)
                ->unit_price->toBe($price)
                ->status->toBe(CustomerOrderLineStatus::Ordered)
                ->note->toContain('SOFA-A')
                ->and($this->order->lines()->count())->toBe(1);
        });

        it('garde la partie réservée et crée une ligne pour le substitut', function () {
            InventoryStock::factory()->create(['product_id' => $this->product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
            $line = $this->order->addProduct($this->product, 3);
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();

            $replacement = $supplierOrder->substituteLine(SupplierOrderLine::sole(), $this->substitute);

            expect($line->fresh())
                ->product_id->toBe($this->product->id)
                ->quantity_reserved->toBe(1)
                ->quantity_on_order->toBe(0)
                ->status->toBe(CustomerOrderLineStatus::InStock)
                ->supplier_order_line_id->toBeNull();

            expect($this->order->lines()->where('product_id', $this->substitute->id)->sole())
                ->supplier_order_line_id->toBe($replacement->id)
                ->quantity_on_order->toBe(2)
                ->unit_price->toBe($line->unit_price)
                ->status->toBe(CustomerOrderLineStatus::Ordered)
                ->and($this->order->fresh()->balance_due)->toBe(round(3 * $line->unit_price, 2));
        });

        it('garde la partie déjà reçue sur la ligne d\'origine', function () {
            $line = $this->order->addProduct($this->product, 3);
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();
            $supplierOrder->fresh()->receive([SupplierOrderLine::sole()->id => ['quantity' => 1, 'unit_cost' => 5]]);

            $supplierOrder->fresh()->substituteLine(SupplierOrderLine::sole(), $this->substitute);

            expect($line->fresh())
                ->quantity_reserved->toBe(1)
                ->quantity_on_order->toBe(0)
                ->quantity->toBe(1)
                ->status->toBe(CustomerOrderLineStatus::Received)
                ->and($this->order->lines()->where('product_id', $this->substitute->id)->sole()->quantity_on_order)->toBe(2);
        });
    });

    describe('annulation fournisseur', function () {
        it('annule la ligne client sans stock réservé quand la commande fournisseur est annulée', function () {
            $line = $this->order->addProduct(Product::factory()->create());
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();

            $supplierOrder->cancel();

            expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Cancelled)
                ->and($this->order->fresh()->balance_due)->toBe(0.0);
        });

        it('annule aussi pour le client un brouillon fournisseur annulé', function () {
            $line = $this->order->addProduct(Product::factory()->create());

            SupplierOrder::sole()->cancel();

            expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::Cancelled);
        });

        it('garde le stock réservé et retire seulement la partie commandée', function () {
            $product = Product::factory()->create();
            InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => 1, 'quantity_reserved' => 0]);
            $line = $this->order->addProduct($product, 3);
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();

            $supplierOrder->cancel();

            expect($line->fresh())
                ->quantity_reserved->toBe(1)
                ->quantity_on_order->toBe(0)
                ->quantity->toBe(1)
                ->status->toBe(CustomerOrderLineStatus::InStock)
                ->supplier_order_line_id->toBeNull()
                ->and($this->order->fresh()->balance_due)->toBe($line->unit_price);
        });

        it('répercute l\'annulation confirmée d\'une ligne en gardant ce qui est reçu', function () {
            $line = $this->order->addProduct(Product::factory()->create(), 3);
            $supplierOrder = SupplierOrder::sole();
            $supplierOrder->send();
            $supplierLine = SupplierOrderLine::sole();
            $supplierOrder->fresh()->receive([$supplierLine->id => ['quantity' => 1, 'unit_cost' => 5]]);

            $supplierOrder->fresh()->requestLineCancellation($supplierLine->fresh());
            $supplierOrder->fresh()->confirmLineCancellation($supplierLine->fresh());

            expect($line->fresh())
                ->quantity_reserved->toBe(1)
                ->quantity_on_order->toBe(0)
                ->quantity->toBe(1)
                ->status->toBe(CustomerOrderLineStatus::Received);
        });
    });

    describe('réception fournisseur', function () {
        beforeEach(function () {
            $this->product = Product::factory()->create();
            $this->line = $this->order->addProduct($this->product, 3);
            $this->supplierOrder = SupplierOrder::sole();
            $this->supplierOrder->send();
            $this->supplierLine = SupplierOrderLine::sole();
        });

        it('réserve au client les unités reçues et garde le reste en commande', function () {
            $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 2, 'unit_cost' => 5]]);

            expect($this->line->fresh())
                ->quantity_reserved->toBe(2)
                ->quantity_on_order->toBe(1)
                ->quantity->toBe(3)
                ->status->toBe(CustomerOrderLineStatus::Ordered)
                ->and($this->product->inventoryStock()->sole())
                ->quantity_in_stock->toBe(0)
                ->quantity_reserved->toBe(2)
                ->quantity_on_order->toBe(1);
        });

        it('passe la ligne client à « Reçu » quand tout est reçu', function () {
            $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 3, 'unit_cost' => 5]]);

            expect($this->line->fresh())
                ->quantity_reserved->toBe(3)
                ->quantity_on_order->toBe(0)
                ->status->toBe(CustomerOrderLineStatus::Received);
        });

        it('libère la réservation au renversement d\'une réception', function () {
            $reception = $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 3, 'unit_cost' => 5]]);

            $this->supplierOrder->fresh()->reverseReceipt($reception->lines()->sole(), 1);

            expect($this->line->fresh())
                ->quantity_reserved->toBe(2)
                ->quantity_on_order->toBe(1)
                ->status->toBe(CustomerOrderLineStatus::Ordered)
                ->and($this->product->inventoryStock()->sole())
                ->quantity_in_stock->toBe(0)
                ->quantity_reserved->toBe(2)
                ->quantity_on_order->toBe(1);
        });

        it('refuse de renverser une réception déjà livrée au client', function () {
            $reception = $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 3, 'unit_cost' => 5]]);
            $this->line->update(['status' => CustomerOrderLineStatus::Delivered]);

            expect(fn () => $this->supplierOrder->fresh()->reverseReceipt($reception->lines()->sole(), 1))
                ->toThrow(DomainException::class);

            expect($this->supplierLine->fresh()->quantity_received)->toBe(3)
                ->and($this->line->fresh()->quantity_reserved)->toBe(3);
        });

        it('ne compte que ce qui reste à recevoir quand la quantité fournisseur change après une réception', function () {
            $this->supplierOrder->fresh()->receive([$this->supplierLine->id => ['quantity' => 1, 'unit_cost' => 5]]);

            $this->supplierOrder->fresh()->updateLine($this->supplierLine->fresh(), 4, $this->supplierLine->unit_cost);

            expect($this->line->fresh())
                ->quantity_reserved->toBe(1)
                ->quantity_on_order->toBe(3)
                ->quantity->toBe(4);
        });
    });
});
