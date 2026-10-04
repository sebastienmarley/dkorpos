<?php

namespace Database\Factories;

use App\Enums\SupplierType;
use App\Models\PriceList;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory()->state(['type' => SupplierType::Product]),
            'starts_on' => today()->toDateString(),
            'ends_on' => PriceList::defaultEndDate()->toDateString(),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
