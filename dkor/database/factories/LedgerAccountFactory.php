<?php

namespace Database\Factories;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('6###'),
            'name' => fake()->words(2, true),
            'type' => LedgerAccountType::Expense,
            'system_key' => null,
        ];
    }
}
