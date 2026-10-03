<?php

namespace Database\Factories;

use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrder>
 */
class SupplierOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SupplierType::Product,
            'supplier_id' => fn (array $attributes) => Supplier::factory()->state(['type' => $attributes['type']]),
            'status' => SupplierOrderStatus::Draft,
            'notes' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(['type' => SupplierType::Service]);
    }

    public function status(SupplierOrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
