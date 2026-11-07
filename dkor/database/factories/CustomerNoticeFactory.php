<?php

namespace Database\Factories;

use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use App\Models\customer;
use App\Models\CustomerNotice;
use App\Models\CustomerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerNotice>
 */
class CustomerNoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'customer_id' => fn (array $attributes) => CustomerOrder::find($attributes['customer_order_id'])?->customer_id ?? customer::factory(),
            'type' => CustomerNoticeType::OrderSummary,
            'recipient' => fake()->safeEmail(),
            'status' => CustomerNoticeStatus::Sent,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => CustomerNoticeStatus::Failed, 'sent_at' => null, 'failed_at' => now()]);
    }
}
