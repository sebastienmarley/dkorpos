<?php

namespace Database\Factories;

use App\Models\InventoryCountScan;
use App\Models\InventoryCountSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountScan>
 */
class InventoryCountScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_count_sheet_id' => InventoryCountSheet::factory(),
            'code' => fake()->ean13(),
            'quantity' => 1,
        ];
    }
}
