<?php

namespace Database\Factories;

use App\Enums\InventoryCountPass;
use App\Enums\InventoryStatus;
use App\Models\InventoryCount;
use App\Models\InventoryCountSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountSheet>
 */
class InventoryCountSheetFactory extends Factory
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
            'pass' => InventoryCountPass::A,
        ];
    }
}
