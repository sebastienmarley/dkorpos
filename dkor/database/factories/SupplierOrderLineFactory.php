<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOrderLine>
 */
class SupplierOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_order_id' => SupplierOrder::factory(),
            'product_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => fake()->numberBetween(1, 20),
            'unit_cost' => fake()->randomFloat(2, 1, 500),
        ];
    }

    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn () => [
            'product_id' => $product instanceof Product ? $product->id : Product::factory(),
            'description' => null,
        ]);
    }
}
