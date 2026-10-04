<?php

namespace Database\Factories;

use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Rules\UniqueCleanProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListItem>
 */
class PriceListItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = strtoupper(fake()->bothify('??-###'));

        return [
            'price_list_list_id' => PriceListList::factory(),
            'model' => $model,
            'clean_model' => UniqueCleanProductModel::clean($model),
            'cost' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
