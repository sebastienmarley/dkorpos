<?php

namespace Database\Factories;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryStatus;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAdjustment>
 */
class InventoryAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'reason' => InventoryAdjustmentReason::Breakage,
            'status' => InventoryStatus::InStock,
            'is_entry' => false,
            'quantity' => 1,
            'note' => fake()->sentence(3),
        ];
    }
}
