<?php

namespace Database\Factories;

use App\Models\DeliveryTruck;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryTruck>
 */
class DeliveryTruckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => __('Camion').' '.fake()->unique()->numberBetween(1, 999),
            'capacity_per_period' => 3,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
