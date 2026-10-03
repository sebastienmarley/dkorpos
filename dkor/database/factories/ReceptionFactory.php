<?php

namespace Database\Factories;

use App\Models\Reception;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reception>
 */
class ReceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'received_at' => now(),
            'reference' => fake()->optional()->bothify('BL-####'),
        ];
    }
}
