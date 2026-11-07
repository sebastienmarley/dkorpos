<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductDemo;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDemo>
 */
class ProductDemoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'price' => Money::of(fake()->numberBetween(5000, 100000)),
            'note' => fake()->optional()->sentence(4),
            'is_as_is' => false,
        ];
    }

    public function asIs(): static
    {
        return $this->state(['is_as_is' => true]);
    }
}
