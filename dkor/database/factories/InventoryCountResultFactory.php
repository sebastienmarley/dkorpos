<?php

namespace Database\Factories;

use App\Enums\InventoryCountResultSource;
use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountResult;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountResult>
 */
class InventoryCountResultFactory extends Factory
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
            'status' => InventoryStatus::InDemo,
            'product_id' => Product::factory(),
            'quantity' => 1,
            'source' => InventoryCountResultSource::Agreed,
        ];
    }
}
