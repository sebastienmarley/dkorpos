<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUpc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUpc>
 */
class ProductUpcFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'upc' => fake()->numerify('############'),
        ];
    }
}
