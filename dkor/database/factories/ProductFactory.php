<?php

namespace Database\Factories;

use App\Enums\SupplierType;
use App\Models\Product;
use App\Models\Supplier;
use App\Rules\UniqueCleanProductModel;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory()->state(['type' => SupplierType::Product]),
            'model' => fake()->words(3, true),
            'clean_model' => fn (array $attributes): string => UniqueCleanProductModel::clean($attributes['model']),
            'supplier_model' => fake()->optional()->bothify('??-####'),
            'cost' => Money::of(fake()->numberBetween(100, 50000)),
        ];
    }
}
