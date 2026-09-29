<?php

namespace Database\Factories;

use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\WeekTemplate;
use App\Models\WeekTemplateEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeekTemplateEntry>
 */
class WeekTemplateEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'week_template_id' => WeekTemplate::factory(),
            'user_id' => User::factory(),
            'shift_template_id' => ShiftTemplate::factory(),
            'weekday' => fake()->numberBetween(0, 6),
        ];
    }
}
