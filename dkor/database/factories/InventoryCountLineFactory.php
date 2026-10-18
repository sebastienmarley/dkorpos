<?php

namespace Database\Factories;

use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountLine>
 */
class InventoryCountLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_count_id' => InventoryCount::factory(),
            'product_id' => Product::factory(),
            'status' => InventoryStatus::InStock,
        ];
    }
}
