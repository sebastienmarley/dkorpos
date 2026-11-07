<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductClearance>
 */
class ProductClearanceFactory extends Factory
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
        ];
    }

    public function ended(): static
    {
        return $this->state(['ended_at' => now()]);
    }
}
