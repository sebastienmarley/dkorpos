<?php

namespace Database\Factories;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderPayment;
use App\Models\CustomerPaymentMethod;
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
            'amount' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
