<?php

namespace Database\Factories;

use App\Enums\Province;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province' => Province::Quebec,
            'name' => fake()->unique()->lexify('Taxe ????'),
            'rate' => 5,
            'is_compound' => false,
            'start_date' => '2020-01-01',
            'end_date' => Tax::DEFAULT_END_DATE,
        ];
    }
}
