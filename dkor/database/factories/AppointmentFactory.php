<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'hour' => fake()->numberBetween(8, 18),
            'title' => fake()->sentence(3),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
