<?php

namespace Database\Factories;

use App\Models\MerchantPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantPaymentMethod>
 */
class MerchantPaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Chèque', 'TEF', 'Carte de crédit', 'Virement bancaire', 'Comptant']),
            'is_active' => true,
        ];
    }
}
