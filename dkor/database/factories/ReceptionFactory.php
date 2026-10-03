<?php

namespace Database\Factories;

use App\Enums\ReceptionStatus;
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
            'status' => ReceptionStatus::Completed,
            'received_at' => now(),
            'reference' => fake()->optional()->bothify('BL-####'),
        ];
    }
}
