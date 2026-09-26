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
            'adress' => fake()->streetAddress(),
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
