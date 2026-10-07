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
