<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderTax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderTax>
 */
class CustomerOrderTaxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'name' => 'TPS',
            'rate' => 5,
            'is_compound' => false,
            'amount' => 0,
        ];
    }
}
