<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'from_status' => InventoryStatus::InStock,
            'to_status' => InventoryStatus::InDemo,
            'quantity' => fake()->numberBetween(1, 5),
            'type' => InventoryMovementType::Transfer,
        ];
    }
}
