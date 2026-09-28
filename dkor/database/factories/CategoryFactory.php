<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => fake()->optional()->randomElement(Department::pluck('id')->toArray()) ?? Department::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
