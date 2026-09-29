<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('+1 day', '+2 years')->format('Y-m-d'),
            'name' => fake()->unique()->words(2, true),
            'is_closed' => true,
        ];
    }

    public function open(): static
    {
        return $this->state(['is_closed' => false]);
    }
}
