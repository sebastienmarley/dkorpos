<?php

use App\Enums\InventoryStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\Store;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Montant (Money) égal au nombre de cents donné (ADR 0008).
 */
expect()->extend('toBeMoney', function (int $cents) {
    expect($this->value)->toBeInstanceOf(Money::class)
        ->and($this->value->cents())->toBe($cents);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Produit en stock (avec ses unités d'inventaire) ajouté à la commande, au prix vendant donné (en dollars).
 */
function stockedLine(CustomerOrder $order, int $inStock, int $quantity, float $price): CustomerOrderLine
{
    $product = Product::factory()->create();
    $storeId = $order->stockStoreId();
    InventoryStock::factory()->create(['product_id' => $product->id, 'store_id' => $storeId, 'quantity_in_stock' => $inStock, 'quantity_reserved' => 0]);
    InventoryUnit::factory()->count($inStock)->create(['product_id' => $product->id, 'store_id' => $storeId, 'delivered_at' => null]);

    $line = $order->addProduct($product, $quantity);
    $order->updateLine($line, $line->quantity_reserved, $line->quantity_on_order, Money::parse($price), null);

    return $line->fresh();
}

/**
 * Ligne ramassée et entièrement payée (quantité au prix donné).
 */
function pickedUpLine(CustomerOrder $order, int $quantity, float $price, CustomerPaymentMethod $method): CustomerOrderLine
{
    $line = stockedLine($order, $quantity, $quantity, $price);
    $order->pickUp([$line->id => $quantity], [['method_id' => $method->id, 'amount' => $order->fresh()->balance_due]]);

    return $line->fresh();
}

/**
 * Invariants d'inventaire d'un produit, magasin par magasin (ADR 0011 et 0012) : journal = quantités (I1), aucune quantité négative (I2),
 * unités en main = états valorisés (I3), réservé = commandes clients (I4), en commande = commandes fournisseurs (I5),
 * « en commande client » à 0 (I6).
 * À utiliser pour un produit dont tout le stock est entré par le journal (pas par une factory).
 */
function expectInventoryIntegrity(Product $product): void
{
    $storeIds = InventoryStock::where('product_id', $product->id)->pluck('store_id');

    expect($storeIds)->not->toBeEmpty();

    foreach ($storeIds as $storeId) {
        $stock = InventoryStock::where('product_id', $product->id)->where('store_id', $storeId)->sole();
        $movements = InventoryMovement::where('product_id', $product->id)->where('store_id', $storeId);

        foreach (InventoryStatus::cases() as $status) {
            $net = (int) (clone $movements)->where('to_status', $status)->sum('quantity')
                - (int) (clone $movements)->where('from_status', $status)->sum('quantity');

            expect($stock->quantityFor($status))->toBe($net, "I1 : journal ≠ quantité « {$status->value} » (magasin {$storeId})")
                ->toBeGreaterThanOrEqual(0);
        }

        $valued = collect(InventoryStatus::cases())->filter->isValued()->sum(fn (InventoryStatus $status): int => $stock->quantityFor($status));

        expect(InventoryUnit::where('product_id', $product->id)->where('store_id', $storeId)->inHand()->count())->toBe($valued, "I3 : unités en main ≠ états valorisés (magasin {$storeId})");

        $reserved = (int) CustomerOrderLine::where('product_id', $product->id)->whereNull('part_id')
            ->whereHas('order', fn ($order) => $order->whereIn('store_id', Store::query()->where('id', $storeId)->orWhere('warehouse_store_id', $storeId)->select('id')))
            ->sum('quantity_reserved');
        $outstanding = SupplierOrderLine::with('order')->where('product_id', $product->id)->get()
            ->filter(fn (SupplierOrderLine $line): bool => $line->order->status->isOpen() && $line->order->store_id === $storeId)
            ->sum(fn (SupplierOrderLine $line): int => $line->quantity_outstanding);

        expect($stock->quantity_reserved + $stock->quantity_in_delivery)->toBe($reserved, "I4 : réservé ≠ commandes clients (magasin {$storeId})")
            ->and($stock->quantity_on_order)->toBe($outstanding, "I5 : en commande ≠ commandes fournisseurs (magasin {$storeId})")
            ->and($stock->quantity_customer_order)->toBe(0, 'I6 : « en commande client » non utilisé');
    }
}

/**
 * Commande fournisseur envoyée puis reçue au coût donné (en dollars).
 */
function receiveProduct(Product $product, int $quantity, float $cost): ReceptionLine
{
    $order = SupplierOrder::factory()->create(['supplier_id' => $product->supplier_id]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse($cost)]);
    $order->markPending();
    $order->fresh()->send();

    return Reception::record($order->supplier, [$line->id => ['quantity' => $quantity]])->lines()->sole();
}

/**
 * Magasin dont le stock sert les tests d'inventaire : celui que l'application prend par défaut pour l'usager connecté
 * (son magasin, sinon le premier magasin physique), créé au besoin (ADR 0012).
 */
function testStoreId(): int
{
    return Store::defaultIdFor(auth()->user()) ?? Store::factory()->create()->id;
}
