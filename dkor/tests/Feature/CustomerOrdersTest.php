<?php

use App\Livewire\CustomerOrders\Index;
use App\Models\customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customers.view', 'customers.create']);
    $this->actingAs($this->user);
});

it('trouve un client existant et le sélectionne', function () {
    $customer = customer::factory()->create(['firstname' => 'Marguerite', 'lastname' => 'Tremblay']);

    Livewire::test(Index::class)
        ->call('openCustomerModal')
        ->set('customerSearch', 'Margu')
        ->assertSee('Tremblay')
        ->call('selectCustomer', $customer->id)
        ->assertSet('selectedCustomerId', $customer->id)
        ->assertSet('showCustomerModal', false);
});

it('sélectionne le client créé via le formulaire client', function () {
    $customer = customer::factory()->create();

    Livewire::test(Index::class)
        ->dispatch('customer-saved', id: $customer->id)
        ->assertSet('selectedCustomerId', $customer->id);
});

it('permet de retirer le client sélectionné', function () {
    $customer = customer::factory()->create();

    Livewire::test(Index::class)
        ->call('selectCustomer', $customer->id)
        ->call('clearCustomer')
        ->assertSet('selectedCustomerId', null)
        ->assertSet('customerSearch', '');
});

it('cherche un produit par ID sans fournisseur', function () {
    $product = Product::factory()->create();
    Product::factory()->create(['model' => 'AUTRE']);

    $component = Livewire::test(Index::class)->set('productSearch', (string) $product->id);

    expect($component->instance()->getProductResults()->pluck('id')->all())->toBe([$product->id]);

    $component->set('productSearch', 'AUTRE');
    expect($component->instance()->getProductResults())->toBeEmpty();
});

it('cherche un produit par modèle dans un fournisseur', function () {
    $product = Product::factory()->create(['model' => 'SOFA-100']);
    Product::factory()->create(['model' => 'SOFA-200']);

    $component = Livewire::test(Index::class)
        ->set('productSupplierId', (string) $product->supplier_id)
        ->set('productSearch', 'SOFA');

    expect($component->instance()->getProductResults()->pluck('id')->all())->toBe([$product->id]);
});

it('ajoute et retire un produit', function () {
    $product = Product::factory()->create();

    Livewire::test(Index::class)
        ->call('addProduct', $product->id)
        ->assertSet('productIds', [$product->id])
        ->call('removeProduct', $product->id)
        ->assertSet('productIds', []);
});

it('crée le produit depuis la liste de prix du fournisseur et l\'ajoute', function () {
    $this->user->givePermissionTo('products.create');
    $priceList = PriceList::factory()->create(['starts_on' => today()->subDay(), 'ends_on' => today()->addMonth()]);
    $list = PriceListList::factory()->create(['price_list_id' => $priceList->id]);
    $item = PriceListItem::factory()->create(['price_list_list_id' => $list->id, 'model' => 'TABLE-9', 'clean_model' => 'TABLE9']);

    $component = Livewire::test(Index::class)
        ->set('productSupplierId', (string) $priceList->supplier_id)
        ->set('priceListSearch', 'TABLE');

    expect($component->instance()->getPriceListResults()->pluck('id')->all())->toBe([$item->id]);

    $component->call('addFromPriceList', $item->id);

    $product = Product::where('supplier_id', $priceList->supplier_id)->where('model', 'TABLE-9')->firstOrFail();
    expect($component->get('productIds'))->toBe([$product->id]);
});

it('refuse la création depuis la liste de prix sans permission', function () {
    $priceList = PriceList::factory()->create(['starts_on' => today()->subDay(), 'ends_on' => today()->addMonth()]);
    $list = PriceListList::factory()->create(['price_list_id' => $priceList->id]);
    $item = PriceListItem::factory()->create(['price_list_list_id' => $list->id]);

    Livewire::test(Index::class)
        ->set('productSupplierId', (string) $priceList->supplier_id)
        ->call('addFromPriceList', $item->id)
        ->assertForbidden();
});

it('ajoute le produit rattaché à un UPC scanné', function () {
    $product = Product::factory()->create();
    $product->upcs()->create(['upc' => '012345678905']);

    Livewire::test(Index::class)
        ->set('upcScan', '012345678905')
        ->call('scanUpc')
        ->assertSet('productIds', [$product->id])
        ->assertSet('upcScan', '')
        ->set('upcScan', '012345678905')
        ->call('scanUpc')
        ->assertSet('productIds', [$product->id]);
});

it('signale un UPC inconnu', function () {
    Livewire::test(Index::class)
        ->set('upcScan', '999')
        ->call('scanUpc')
        ->assertHasErrors('upcScan')
        ->assertSet('productIds', []);
});

it('définit l\'utilisateur connecté comme vendeur par défaut à 100 %', function () {
    Livewire::test(Index::class)
        ->assertSet('salespeople', [['user_id' => $this->user->id, 'percent' => 100]]);
});

it('cache le bouton vendeur et refuse la modification sans permission', function () {
    Livewire::test(Index::class)
        ->assertDontSee('Ajouter un vendeur')
        ->call('openSalespeopleModal')
        ->assertForbidden();
});

describe('gestion des vendeurs', function () {
    beforeEach(function () {
        $this->user->givePermissionTo('customer_orders.assign_salespeople');
        $this->other = User::factory()->withRole('visiteur')->create();
        $this->third = User::factory()->withRole('visiteur')->create();
    });

    it('affiche le bouton vendeur', function () {
        Livewire::test(Index::class)->assertSee('Ajouter un vendeur');
    });

    it('répartit la vente entre deux vendeurs', function () {
        Livewire::test(Index::class)
            ->call('openSalespeopleModal')
            ->set('salespeopleDraft', [
                ['user_id' => $this->user->id, 'percent' => 75],
                ['user_id' => $this->other->id, 'percent' => 25],
            ])
            ->call('saveSalespeople')
            ->assertHasNoErrors()
            ->assertSet('salespeople', [
                ['user_id' => $this->user->id, 'percent' => 75],
                ['user_id' => $this->other->id, 'percent' => 25],
            ]);
    });

    it('ajoute 1 au premier vendeur quand la somme est de 99', function () {
        Livewire::test(Index::class)
            ->call('openSalespeopleModal')
            ->set('salespeopleDraft', [
                ['user_id' => $this->user->id, 'percent' => 33],
                ['user_id' => $this->other->id, 'percent' => 33],
                ['user_id' => $this->third->id, 'percent' => 33],
            ])
            ->call('saveSalespeople')
            ->assertHasNoErrors()
            ->assertSet('salespeople.0.percent', 34)
            ->assertSet('salespeople.1.percent', 33)
            ->assertSet('salespeople.2.percent', 33);
    });

    it('refuse une somme différente de 100', function () {
        Livewire::test(Index::class)
            ->call('openSalespeopleModal')
            ->set('salespeopleDraft', [
                ['user_id' => $this->user->id, 'percent' => 50],
                ['user_id' => $this->other->id, 'percent' => 25],
            ])
            ->call('saveSalespeople')
            ->assertHasErrors('salespeopleDraft')
            ->assertSet('salespeople', [['user_id' => $this->user->id, 'percent' => 100]]);
    });

    it('refuse le même vendeur deux fois', function () {
        Livewire::test(Index::class)
            ->call('openSalespeopleModal')
            ->set('salespeopleDraft', [
                ['user_id' => $this->user->id, 'percent' => 50],
                ['user_id' => $this->user->id, 'percent' => 50],
            ])
            ->call('saveSalespeople')
            ->assertHasErrors('salespeopleDraft.1.user_id');
    });

    it('limite à 3 vendeurs', function () {
        Livewire::test(Index::class)
            ->call('openSalespeopleModal')
            ->call('addSalesperson')
            ->call('addSalesperson')
            ->call('addSalesperson')
            ->assertCount('salespeopleDraft', 3);
    });
});

it('désactive dans les autres lignes un vendeur déjà choisi', function () {
    $this->user->givePermissionTo('customer_orders.assign_salespeople');
    $other = User::factory()->withRole('visiteur')->create();

    Livewire::test(Index::class)
        ->call('openSalespeopleModal')
        ->call('addSalesperson')
        ->set('salespeopleDraft.1.user_id', $other->id)
        ->assertSeeHtmlInOrder(['value="'.$other->id.'"', 'disabled']);
});
