<?php

use App\Livewire\Products\Index;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

it('ne charge la table qu\'avec une recherche ou un filtre qui trouve des produits', function () {
    $product = Product::factory()->create(['model' => 'CHAISE-ALPHA']);

    Livewire::test(Index::class)
        ->assertDontSeeHtml('data-flux-table')
        ->assertDontSee('Aucun produit trouvé pour ces critères.')
        ->set('search', 'introuvable')
        ->assertDontSeeHtml('data-flux-table')
        ->assertSee('Aucun produit trouvé pour ces critères.')
        ->set('search', 'alpha')
        ->assertSeeHtml('data-flux-table')
        ->assertSeeHtml('data-href="'.route('products.show', $product).'"')
        ->assertDontSeeHtml('<a href="'.route('products.show', $product).'"')
        ->assertSee('1 produit');
});

it('cherche par ID, modèle ou modèle fournisseur, mais plus par nom de fournisseur', function () {
    $supplier = Supplier::factory()->create(['name' => 'Meubles Gaspé']);
    $byModel = Product::factory()->create(['model' => 'TABLE-ROND', 'supplier_model' => 'X-1', 'supplier_id' => $supplier->id]);
    $bySupplierModel = Product::factory()->create(['model' => 'BUFFET', 'supplier_model' => 'GSP-ROND-2']);
    $other = Product::factory()->create(['model' => 'LAMPE', 'supplier_model' => 'L-9']);

    $ids = fn (string $search): array => Livewire::test(Index::class)->set('search', $search)->instance()->getProducts()->pluck('id')->all();

    expect($ids('rond'))->toEqualCanonicalizing([$byModel->id, $bySupplierModel->id])
        ->and($ids((string) $other->id))->toContain($other->id)
        ->and($ids('Gaspé'))->toBe([]);

    expect(Livewire::test(Index::class)->set('supplierId', (string) $supplier->id)->instance()->getProducts()->pluck('id')->all())
        ->toBe([$byModel->id]);
});

it('montre le stock des magasins de l\'usager et filtre par disponibilité', function () {
    $rimouski = Store::factory()->create(['name' => 'Rimouski']);
    $matane = Store::factory()->create(['name' => 'Matane', 'warehouse_store_id' => $rimouski->id]);
    $gaspe = Store::factory()->create(['name' => 'Gaspé']);
    $sofa = Product::factory()->create(['model' => 'SOFA-1']);
    $lamp = Product::factory()->create(['model' => 'LAMPE-1']);
    $chair = Product::factory()->create(['model' => 'CHAISE-1']);
    InventoryStock::factory()->create(['product_id' => $sofa->id, 'store_id' => $rimouski->id, 'quantity_in_stock' => 3, 'quantity_in_demo' => 1, 'quantity_on_order' => 0, 'quantity_reserved' => 2, 'quantity_customer_order' => 1]);
    InventoryStock::factory()->create(['product_id' => $sofa->id, 'store_id' => $gaspe->id, 'quantity_in_stock' => 4, 'quantity_in_demo' => 0, 'quantity_on_order' => 2, 'quantity_reserved' => 0]);
    InventoryStock::factory()->create(['product_id' => $lamp->id, 'store_id' => $gaspe->id, 'quantity_in_stock' => 5, 'quantity_in_demo' => 0, 'quantity_on_order' => 0, 'quantity_reserved' => 0]);
    $emptyChair = Product::factory()->create(['model' => 'CHAISE-VIDE']);
    InventoryStock::factory()->create(['product_id' => $chair->id, 'store_id' => $rimouski->id, 'quantity_in_stock' => 1, 'quantity_in_demo' => 0, 'quantity_on_order' => 0, 'quantity_reserved' => 0]);
    InventoryStock::factory()->create(['product_id' => $emptyChair->id, 'store_id' => $rimouski->id, 'quantity_in_stock' => 0, 'quantity_in_demo' => 0, 'quantity_on_order' => 0, 'quantity_reserved' => 0]);
    ProductClearance::factory()->create(['product_id' => $chair->id, 'store_id' => $rimouski->id]);
    ProductClearance::factory()->create(['product_id' => $emptyChair->id, 'store_id' => $rimouski->id]);

    $ids = fn (string $availability): array => Livewire::test(Index::class)->set('availabilityFilter', $availability)->instance()->getProducts()->pluck('id')->all();

    // Avec stores.all : tous les magasins.
    expect($ids(Index::AVAILABILITY_IN_STOCK))->toEqualCanonicalizing([$sofa->id, $lamp->id, $chair->id])
        ->and($ids(Index::AVAILABILITY_CLEARANCE))->toBe([$chair->id])
        ->and($ids(Index::AVAILABILITY_ON_ORDER))->toBe([$sofa->id])
        ->and(Livewire::test(Index::class)->set('search', 'SOFA')->instance()->getProducts()->first()->stock_in_stock)->toEqual(7);

    // Vendeur de Matane : le stock de son entrepôt (Rimouski) seulement.
    $this->actingAs(User::factory()->withRole('salesman')->create(['store_id' => $matane->id]));

    expect($ids(Index::AVAILABILITY_IN_STOCK))->toEqualCanonicalizing([$sofa->id, $chair->id])
        ->and($ids(Index::AVAILABILITY_IN_DEMO))->toBe([$sofa->id])
        ->and($ids(Index::AVAILABILITY_ON_ORDER))->toBe([])
        ->and($ids(Index::AVAILABILITY_RESERVED))->toBe([$sofa->id])
        ->and($ids(Index::AVAILABILITY_CLEARANCE))->toBe([$chair->id]);

    Livewire::test(Index::class)->set('search', 'SOFA')
        ->assertSee('Démo 1')
        ->assertSee('+1 en commande')
        ->assertViewHas('products', fn ($products): bool => (int) $products->first()->stock_in_stock === 3 && (int) $products->first()->stock_reserved === 2);
});
