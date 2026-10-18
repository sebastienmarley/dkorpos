<?php

namespace Database\Factories;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntryLine>
 */
class LedgerEntryLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_entry_id' => LedgerEntry::factory(),
            'ledger_account_id' => LedgerAccount::factory(),
            'debit' => Money::zero(),
            'credit' => Money::zero(),
        ];
    }
}
