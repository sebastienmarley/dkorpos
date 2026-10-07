<?php

namespace Database\Factories;

use App\Enums\CustomerOrderStatus;
use App\Models\customer;
use App\Models\CustomerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrder>
 */
class CustomerOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => customer::factory(),
            'status' => CustomerOrderStatus::New,
            'balance_due' => 0,
        ];
    }

    public function status(CustomerOrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
