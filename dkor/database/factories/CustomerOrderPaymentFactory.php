<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderPayment;
use App\Models\CustomerPaymentMethod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderPayment>
 */
class CustomerOrderPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'customer_payment_method_id' => CustomerPaymentMethod::factory(),
            'amount' => Money::of(fake()->numberBetween(1000, 50000)),
        ];
    }
}
