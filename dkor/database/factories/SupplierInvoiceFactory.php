<?php

namespace Database\Factories;

use App\Models\Reception;
use App\Models\SupplierInvoice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = Money::of(fake()->numberBetween(1000, 100000));

        return [
            'reception_id' => Reception::factory(),
            'supplier_id' => fn (array $attributes) => Reception::query()->whereKey($attributes['reception_id'])->firstOrFail()->supplier_id,
            'invoice_number' => fake()->unique()->bothify('F-#####'),
            'invoice_date' => now()->toDateString(),
            'merchandise_total' => $total,
            'computed_total' => $total,
            'invoice_total' => $total,
        ];
    }
}
