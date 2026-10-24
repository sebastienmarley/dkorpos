<?php

namespace Database\Factories;

use App\Models\InventoryLocation;
use App\Models\InventoryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_zone_id' => InventoryZone::factory(),
            'code' => fake()->unique()->bothify('?-##-#'),
            'description' => null,
            'is_active' => true,
        ];
    }
}
