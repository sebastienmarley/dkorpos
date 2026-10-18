<?php

namespace Database\Factories;

use App\Enums\LabelReason;
use App\Models\LabelRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabelRequest>
 */
class LabelRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'reason' => LabelReason::Manual,
        ];
    }

    public function printed(): static
    {
        return $this->state(['printed_at' => now()]);
    }
}
