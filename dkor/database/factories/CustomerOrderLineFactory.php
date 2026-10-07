<?php

namespace Database\Factories;

use App\Enums\CustomerOrderLineStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderLine>
 */
class CustomerOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 4),
            'unit_price' => fake()->randomFloat(2, 10, 2000),
            'note' => null,
            'status' => CustomerOrderLineStatus::InStock,
        ];
    }

    public function status(CustomerOrderLineStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
