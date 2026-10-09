<?php

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryStock;
use App\Models\InventoryUnit;
use App\Models\Product;
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
 * Produit en stock (avec ses unités d'inventaire) ajouté à la commande, au prix vendant donné.
 */
function stockedLine(CustomerOrder $order, int $inStock, int $quantity, float $price): CustomerOrderLine
{
    $product = Product::factory()->create();
    InventoryStock::factory()->create(['product_id' => $product->id, 'quantity_in_stock' => $inStock, 'quantity_reserved' => 0]);
    InventoryUnit::factory()->count($inStock)->create(['product_id' => $product->id, 'delivered_at' => null]);

    $line = $order->addProduct($product, $quantity);
    $order->updateLine($line, $line->quantity_reserved, $line->quantity_on_order, $price, null);

    return $line->fresh();
}
