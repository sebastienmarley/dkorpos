<?php

namespace Database\Factories;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPaymentAllocation>
 */
class SupplierPaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = Money::of(fake()->numberBetween(1000, 100000));

        return [
            'supplier_payment_id' => SupplierPayment::factory(),
            'supplier_invoice_id' => SupplierInvoice::factory(),
            'amount' => $amount,
            'discount' => Money::zero(),
            'payable_cad' => $amount,
        ];
    }
}
