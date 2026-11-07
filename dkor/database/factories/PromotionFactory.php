<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => PromotionType::Percent,
            'percent' => 10,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'is_active' => true,
        ];
    }

    public function fixedAmount(int $dollars, int $minimumQuantity): static
    {
        return $this->state(['type' => PromotionType::FixedAmount, 'percent' => null, 'amount' => Money::parse($dollars), 'minimum_quantity' => $minimumQuantity]);
    }

    public function taxesPaid(): static
    {
        return $this->state(['type' => PromotionType::TaxesPaid, 'percent' => null]);
    }
}
