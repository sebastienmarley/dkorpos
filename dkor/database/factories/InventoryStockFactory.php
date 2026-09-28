<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity_in_stock' => fake()->numberBetween(0, 100),
            'quantity_on_order' => fake()->numberBetween(0, 20),
            'quantity_in_demo' => fake()->numberBetween(0, 5),
            'quantity_reserved' => fake()->numberBetween(0, 10),
            'quantity_customer_order' => fake()->numberBetween(0, 15),
        ];
    }
}
