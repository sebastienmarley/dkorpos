<?php

namespace Database\Factories;

use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\SupplierOrderLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionLine>
 */
class ReceptionLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reception_id' => Reception::factory(),
            'supplier_order_line_id' => SupplierOrderLine::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'quantity_expected' => fn (array $attributes): int => $attributes['quantity'],
            'checked_at' => now(),
            'unit_cost' => Money::of(fake()->numberBetween(100, 50000)),
        ];
    }
}
