<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'model' => fake()->words(3, true),
            'supplier_model' => fake()->optional()->bothify('??-####'),
            'cost' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
