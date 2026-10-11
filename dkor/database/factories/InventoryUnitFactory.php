<?php

namespace Database\Factories;

use App\Models\InventoryUnit;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryUnit>
 */
class InventoryUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $insertedAt = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'product_id' => Product::factory(),
            'cost' => Money::of(fake()->numberBetween(100, 50000)),
            'inserted_at' => $insertedAt,
            'delivered_at' => fake()->optional()->dateTimeBetween($insertedAt, 'now'),
        ];
    }
}
