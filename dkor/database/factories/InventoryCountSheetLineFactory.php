<?php

namespace Database\Factories;

use App\Models\InventoryCountSheet;
use App\Models\InventoryCountSheetLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountSheetLine>
 */
class InventoryCountSheetLineFactory extends Factory
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
            'product_id' => Product::factory(),
            'quantity' => 1,
        ];
    }
}
