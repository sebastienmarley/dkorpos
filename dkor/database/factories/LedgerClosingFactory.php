<?php

namespace Database\Factories;

use App\Models\LedgerClosing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerClosing>
 */
class LedgerClosingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closed_through' => today()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'user_id' => null,
        ];
    }
}
