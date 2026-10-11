<?php

namespace Database\Factories;

use App\Enums\SupplierType;
use App\Models\Part;
use App\Models\Supplier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory()->state(['type' => SupplierType::Product]),
            'model' => fake()->unique()->bothify('PC-####-??'),
            'description' => fake()->sentence(3),
            'last_cost' => Money::of(fake()->numberBetween(0, 5000)),
        ];
    }
}
