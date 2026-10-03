<?php

namespace Database\Factories;

use App\Models\ReceptionLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoiceLine>
 */
class SupplierInvoiceLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_invoice_id' => SupplierInvoice::factory(),
            'reception_line_id' => ReceptionLine::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_cost' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
