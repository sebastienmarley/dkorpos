<?php

namespace Database\Factories;

use App\Models\WeekTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeekTemplate>
 */
class WeekTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
