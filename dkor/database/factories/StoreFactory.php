<?php

namespace Database\Factories;

use App\Enums\Province;
use App\Enums\StoreType;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => StoreType::Physical,
            'province' => Province::Quebec,
            'phone' => fake()->optional()->numerify('(###)###-####'),
            'email' => fake()->optional()->companyEmail(),
            'is_active' => true,
        ];
    }

    public function virtual(): static
    {
        return $this->state(['type' => StoreType::Virtual]);
    }
}
