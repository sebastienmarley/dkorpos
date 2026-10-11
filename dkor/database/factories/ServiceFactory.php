<?php

namespace Database\Factories;

use App\Models\Service;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description_template' => null,
            'is_internal' => false,
            'selling_price' => null,
            'is_taxable' => true,
            'is_active' => true,
        ];
    }

    public function internal(?Money $price = null): static
    {
        return $this->state(['is_internal' => true, 'selling_price' => $price ?? Money::of(5000)]);
    }
}
