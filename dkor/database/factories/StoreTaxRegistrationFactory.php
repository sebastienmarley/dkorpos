<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreTaxRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreTaxRegistration>
 */
class StoreTaxRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'tax_name' => 'TPS',
            'number' => fake()->numerify('#########RT0001'),
        ];
    }
}
