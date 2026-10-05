<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\PriceListList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListList>
 */
class PriceListListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'name' => fake()->words(2, true),
            'discount_percent' => 0,
        ];
    }
}
