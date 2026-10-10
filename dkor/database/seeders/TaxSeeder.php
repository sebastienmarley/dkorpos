<?php

namespace Database\Seeders;

use App\Enums\Province;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Taxes de départ : TPS et TVQ au Québec, TVH en Ontario. Sans effet sur ce qui existe déjà.
     */
    public function run(): void
    {
        $taxes = [
            [Province::Quebec, 'TPS', '5', '2008-01-01'],
            [Province::Quebec, 'TVQ', '9.975', '2013-01-01'],
            [Province::Ontario, 'TVH', '13', '2010-07-01'],
        ];

        foreach ($taxes as [$province, $name, $rate, $startDate]) {
            if (! Tax::where('province', $province)->where('name', $name)->exists()) {
                Tax::create(['province' => $province, 'name' => $name, 'rate' => $rate, 'start_date' => $startDate]);
            }
        }
    }
}
