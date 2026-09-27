<?php

namespace Database\Factories;

use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(SupplierType::cases()),
            'name' => fake()->company(),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->numerify('(###)###-####'),
            'email' => fake()->optional()->companyEmail(),
            'account_number' => fake()->optional()->numerify('ACCT-#####'),
            'bank_account' => fake()->optional()->numerify('##########'),
            'payment_address' => fake()->optional()->address(),
            'order_email' => fake()->optional()->companyEmail(),
            'orderable' => true,
            'is_active' => true,
        ];
    }
}
