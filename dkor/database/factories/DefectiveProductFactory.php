<?php

namespace Database\Factories;

use App\Enums\DefectiveResolution;
use App\Models\DefectiveProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectiveProduct>
 */
class DefectiveProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => 1,
            'resolution' => DefectiveResolution::Refund,
            'reason' => fake()->sentence(),
        ];
    }
}
