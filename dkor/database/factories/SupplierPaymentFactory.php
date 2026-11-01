<?php

namespace Database\Factories;

use App\Enums\SupplierPaymentMethod;
use App\Models\LedgerAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = Money::of(fake()->numberBetween(1000, 100000));

        return [
            'supplier_id' => Supplier::factory(),
            'ledger_account_id' => fn () => LedgerAccount::forKey(LedgerAccount::CASH_CAD)->id,
            'paid_on' => now()->toDateString(),
            'method' => SupplierPaymentMethod::Cheque,
            'amount' => $amount,
            'amount_cad' => $amount,
        ];
    }
}
