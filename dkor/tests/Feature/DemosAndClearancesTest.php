<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\DefectiveResolution;
use App\Livewire\CustomerOrders\Show;
use App\Livewire\Products\DemosAndClearances;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\ProductDemo;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\TaxSeeder;
use Livewire\Livewire;

/**
 * Produit avec son stock en main et en démo dans le magasin (une unité d'inventaire par article).
 */
function productWithStock(int $storeId, int $inStock, int $inDemo = 0): Product
{
    $product = Product::factory()->create(['cost' => Money::of(10000)]);
    InventoryStock::factory()->create(['product_id' => $product->id, 'store_id' => $storeId, 'quantity_in_stock' => $inStock, 'quantity_in_demo' => $inDemo, 'quantity_reserved' => 0]);
    InventoryUnit::factory()->count($inStock + $inDemo)->create(['product_id' => $product->id, 'store_id' => $storeId, 'delivered_at' => null]);

    return $product->fresh();
}

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    (new TaxSeeder)->run();
    $this->store = Store::factory()->create();
    $this->user = User::factory()->withRole('visiteur')->create(['store_id' => $this->store->id]);
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit', 'products.view', 'clearances.manage']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create(['store_id' => $this->store->id]);
    $this->cash = CustomerPaymentMethod::cash();
});

describe('démonstrateurs', function () {
    it('ne crée pas plus de fiches libres que d\'articles en démo dans le magasin', function () {
        $product = productWithStock($this->store->id, 2, 1);

        $product->declareDemo($this->store->id, Money::parse(700), 'Égratignure', false);

        expect(fn () => $product->declareDemo($this->store->id, Money::parse(650), null, false))->toThrow(DomainException::class, 'déjà sa fiche')
            ->and(fn () => productWithStock($this->store->id, 2)->declareDemo($this->store->id, Money::parse(650), null, false))->toThrow(DomainException::class, 'Aucun article en démo');
    });

    it('vend le démonstrateur à son prix en réservant l\'article en démo', function () {
        $product = productWithStock($this->store->id, 2, 1);
        $demo = $product->declareDemo($this->store->id, Money::parse(700), 'Égratignure côté gauche', false);

        $line = $this->order->addDemo($demo);

        expect($line->fresh())
            ->quantity->toBe(1)
            ->quantity_reserved->toBe(1)
            ->quantity_on_order->toBe(0)
            ->unit_price->toBeMoney(70000)
            ->regular_unit_price->toBeMoney($product->selling_price->cents())
            ->note->toBe('Égratignure côté gauche')
            ->product_demo_id->toBe($demo->id)
            ->and($product->stockAt($this->store->id))
            ->quantity_in_demo->toBe(0)
            ->quantity_in_stock->toBe(2)
            ->quantity_reserved->toBe(1)
            ->and($demo->isTaken())->toBeTrue()
            ->and(fn () => CustomerOrder::factory()->create(['store_id' => $this->store->id])->addDemo($demo))->toThrow(DomainException::class, 'déjà vendu');
    });

    it('remet l\'article en démo et libère la fiche quand la ligne est retirée', function () {
        $product = productWithStock($this->store->id, 0, 1);
        $demo = $product->declareDemo($this->store->id, Money::parse(700), null, false);
        $line = $this->order->addDemo($demo);

        $this->order->removeLine($line);

        expect($product->stockAt($this->store->id))->quantity_in_demo->toBe(1)->quantity_reserved->toBe(0)
            ->and($demo->isTaken())->toBeFalse();
    });

    it('ne change pas la quantité d\'un démonstrateur et ne l\'ajoute pas à une autre ligne', function () {
        $product = productWithStock($this->store->id, 2, 1);
        $line = $this->order->addDemo($product->declareDemo($this->store->id, Money::parse(700), null, false));

        $added = $this->order->addProduct($product);

        expect(fn () => $this->order->adjustLine($line->fresh(), 2, 0))->toThrow(DomainException::class, 'à l\'unité')
            ->and($added->id)->not->toBe($line->id)
            ->and($line->fresh()->quantity)->toBe(1);
    });

    it('refuse le démonstrateur d\'un autre magasin', function () {
        $otherStore = Store::factory()->create();
        $product = productWithStock($otherStore->id, 0, 1);

        expect(fn () => $this->order->addDemo($product->declareDemo($otherStore->id, Money::parse(700), null, false)))
            ->toThrow(DomainException::class, 'autre magasin');
    });

    it('sort le démonstrateur remis en FIFO et refuse le retour d\'un article tel quel, mais pas le dossier défectueux', function () {
        $product = productWithStock($this->store->id, 0, 1);
        $line = $this->order->addDemo($product->declareDemo($this->store->id, Money::parse(500), null, true));

        $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => $this->order->fresh()->balance_due]]);

        expect($line->fresh()->status)->toBe(CustomerOrderLineStatus::PickedUp)
            ->and(InventoryUnit::query()->where('product_id', $product->id)->whereNull('delivered_at')->count())->toBe(0)
            ->and(fn () => $this->order->returnLine($line->fresh(), 1, refund: false))->toThrow(DomainException::class, 'tel quel');

        $defective = $this->order->returnDefective($line->fresh(), 1, 'Moteur bruyant', replace: false);

        expect($defective->resolution)->not->toBe(DefectiveResolution::Replacement);
    });

    it('n\'escompte jamais un démonstrateur par une promotion', function () {
        $product = productWithStock($this->store->id, 0, 1);
        $line = $this->order->addDemo($product->declareDemo($this->store->id, $product->selling_price, null, false));
        $promotion = Promotion::factory()->create();
        $promotion->targets()->create(['target_type' => 'product', 'target_id' => $product->id]);

        expect($promotion->eligibleLinesOf($this->order)->pluck('id'))->not->toContain($line->id);
    });
});

describe('liquidations', function () {
    it('vend le stock en main au prix de liquidation et commande le reste au prix de vente', function () {
        $product = productWithStock($this->store->id, 2);
        $clearance = $product->clearAt($this->store->id, Money::parse(60));

        $clearanceLine = $this->order->addProduct($product, 3);
        $regularLine = $this->order->lines()->whereNull('product_clearance_id')->sole();

        expect($clearanceLine->fresh())
            ->product_clearance_id->toBe($clearance->id)
            ->unit_price->toBeMoney(6000)
            ->regular_unit_price->toBeMoney($product->selling_price->cents())
            ->quantity_reserved->toBe(2)
            ->quantity_on_order->toBe(0)
            ->and($regularLine)
            ->unit_price->toBeMoney($product->selling_price->cents())
            ->quantity_reserved->toBe(0)
            ->quantity_on_order->toBe(1);
    });

    it('additionne un nouvel ajout à la ligne de liquidation tant qu\'il reste du stock', function () {
        $product = productWithStock($this->store->id, 3);
        $product->clearAt($this->store->id, Money::parse(60));

        $first = $this->order->addProduct($product);
        $second = $this->order->addProduct($product);

        expect($second->id)->toBe($first->id)
            ->and($second->fresh()->quantity_reserved)->toBe(2);
    });

    it('ne passe jamais une ligne de liquidation en commande fournisseur', function () {
        $product = productWithStock($this->store->id, 1);
        $product->clearAt($this->store->id, Money::parse(60));
        $line = $this->order->addProduct($product);

        expect(fn () => $this->order->adjustLine($line->fresh(), 1, 1))->toThrow(DomainException::class, 'liquidation');
    });

    it('garde le prix de vente sans liquidation active dans le magasin de la commande', function () {
        $product = productWithStock($this->store->id, 2);
        $product->clearAt($this->store->id, Money::parse(60));
        $product->endClearanceAt($this->store->id);
        ProductClearance::factory()->create(['product_id' => $product->id, 'store_id' => Store::factory()->create()->id, 'price' => Money::parse(50)]);

        $line = $this->order->addProduct($product);

        expect($line)->unit_price->toBeMoney($product->selling_price->cents())->product_clearance_id->toBeNull();
    });

    it('garde le lien avec la liquidation quand le prix est changé à la main', function () {
        $product = productWithStock($this->store->id, 1);
        $product->clearAt($this->store->id, Money::parse(60));
        $line = $this->order->addProduct($product)->fresh();

        $this->order->updateLine($line, 1, 0, Money::parse(55), null);

        expect($line->fresh())->unit_price->toBeMoney(5500)->product_clearance_id->not->toBeNull()->regular_unit_price->toBeMoney($product->selling_price->cents());
    });
});

describe('écrans', function () {
    it('déclare un démonstrateur et met le produit en liquidation depuis la fiche produit', function () {
        $product = productWithStock($this->store->id, 2, 1);

        Livewire::test(DemosAndClearances::class, ['product' => $product])
            ->call('openDemoForm', $this->store->id)
            ->set('demoPrice', '699,00')
            ->set('demoNote', 'Porte bosselée')
            ->set('demoIsAsIs', true)
            ->call('declareDemo')
            ->assertHasNoErrors()
            ->set("clearancePrices.{$this->store->id}", '80')
            ->call('saveClearance', $this->store->id)
            ->assertHasNoErrors();

        expect(ProductDemo::query()->sole())->price->toBeMoney(69900)->note->toBe('Porte bosselée')->is_as_is->toBeTrue()
            ->and($product->activeClearanceAt($this->store->id)?->price)->toBeMoney(8000);
    });

    it('refuse la gestion sans la permission ou pour un autre magasin', function () {
        $otherStore = Store::factory()->create();
        $product = productWithStock($otherStore->id, 1, 1);

        Livewire::test(DemosAndClearances::class, ['product' => $product])
            ->call('openDemoForm', $otherStore->id)
            ->assertForbidden();

        $this->user->revokePermissionTo('clearances.manage');

        Livewire::test(DemosAndClearances::class, ['product' => $product])
            ->call('saveClearance', $this->store->id)
            ->assertForbidden();
    });

    it('offre les démonstrateurs libres et la liquidation du magasin dans la recherche de produits', function () {
        $product = productWithStock($this->store->id, 1, 1);
        $product->clearAt($this->store->id, Money::parse(80));
        $demo = $product->declareDemo($this->store->id, Money::parse(700), 'Porte bosselée', false);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openProductModal')
            ->set('productSearch', (string) $product->id)
            ->assertSee('Liquidation '.Money::parse(80)->format())
            ->assertSee('Porte bosselée')
            ->call('addDemo', $demo->id)
            ->assertSet('showProductModal', false);

        expect($this->order->lines()->sole()->product_demo_id)->toBe($demo->id);
    });

    it('indique le démonstrateur tel quel et la liquidation sur le document client', function () {
        $demoProduct = productWithStock($this->store->id, 0, 1);
        $this->order->addDemo($demoProduct->declareDemo($this->store->id, Money::parse(700), null, true));
        $clearedProduct = productWithStock($this->store->id, 1);
        $clearedProduct->clearAt($this->store->id, Money::parse(80));
        $this->order->addProduct($clearedProduct);

        $this->get(route('customer-orders.print', $this->order))
            ->assertOk()
            ->assertSee('Démonstrateur — vendu tel quel, non retournable')
            ->assertSee('Liquidation')
            ->assertSee('Prix régulier '.$clearedProduct->selling_price->format());
    });

    it('imprime l\'étiquette de liquidation du magasin', function () {
        $product = productWithStock($this->store->id, 1);
        $product->clearAt($this->store->id, Money::parse(80));

        $this->get(route('products.label', ['product' => $product, 'store' => $this->store->id]))
            ->assertOk()
            ->assertSee('Liquidation')
            ->assertSee('80 $');
    });
});
