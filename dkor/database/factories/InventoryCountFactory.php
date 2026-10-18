<?php

namespace Database\Factories;

use App\Enums\InventoryCountStatus;
use App\Models\InventoryCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCount>
 */
class InventoryCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'counted_on' => today(),
            'status' => InventoryCountStatus::InProgress,
        ];
    }
}
