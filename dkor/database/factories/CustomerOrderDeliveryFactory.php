<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Enums\DeliveryStatus;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderDelivery>
 */
class CustomerOrderDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_order_id' => CustomerOrder::factory(),
            'store_id' => fn (array $attributes): mixed => CustomerOrder::query()->whereKey($attributes['customer_order_id'])->value('store_id'),
            'method' => DeliveryMethod::Internal,
            'status' => DeliveryStatus::Scheduled,
            'scheduled_date' => now()->addWeek()->toDateString(),
            'period' => DeliveryPeriod::Am,
            'address_civic' => fake()->buildingNumber(),
            'address_street' => fake()->streetName(),
            'address_city' => fake()->city(),
            'address_province' => 'QC',
            'address_country' => 'CA',
            'address_postal_code' => 'G5L1A1',
            'address_verified_at' => now(),
        ];
    }

    public function on(string $date, DeliveryPeriod $period): static
    {
        return $this->state(['scheduled_date' => $date, 'period' => $period]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => DeliveryStatus::Cancelled, 'cancelled_at' => now()]);
    }
}
