<?php

namespace Database\Factories;

use App\Models\customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<customer>
 */
class customerFactory extends Factory
{
    protected $model = customer::class;

    public function definition(): array
    {
        $area = fake()->numberBetween(200, 999);
        $exchange = fake()->numberBetween(200, 999);
        $subscriber = fake()->numberBetween(1000, 9999);

        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'phone' => "({$area}){$exchange}-{$subscriber}",
            'cellphone' => null,
            'email' => fake()->unique()->safeEmail(),
            'address_civic' => (string) fake()->buildingNumber(),
            'address_street' => fake()->streetName(),
            'address_city' => fake()->city(),
            'address_province' => fake()->randomElement(['QC', 'ON', 'BC', 'AB', 'MB', 'SK', 'NB', 'NS', 'PE', 'NL', 'NT', 'NU', 'YT']),
            'address_country' => 'CA',
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
