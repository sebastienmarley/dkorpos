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
            'address_civic' => fake()->optional()->buildingNumber(),
            'address_street' => fake()->optional()->streetName(),
            'address_city' => fake()->optional()->city(),
            'address_province' => fake()->optional()->randomElement(['QC', 'ON', 'BC', 'AB', 'MB', 'SK', 'NB', 'NS', 'PE', 'NL', 'NT', 'NU', 'YT']),
            'address_country' => 'CA',
            'phone' => fake()->optional()->numerify('(###)###-####'),
            'email' => fake()->optional()->companyEmail(),
            'account_number' => fake()->optional()->numerify('ACCT-#####'),
            'bank_account' => fake()->optional()->numerify('##########'),
            'order_email' => fake()->optional()->companyEmail(),
            'orderable' => true,
            'is_active' => true,
        ];
    }
}
