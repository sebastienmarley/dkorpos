<?php

namespace Database\Factories;

use App\Models\CustomerPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPaymentMethod>
 */
class CustomerPaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Comptant', 'Débit', 'Visa', 'Mastercard', 'American Express', 'Chèque', 'Financement']),
            'is_active' => true,
        ];
    }
}
