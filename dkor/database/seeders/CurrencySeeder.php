<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'CAD', 'name' => 'Dollar canadien', 'rate' => 1],
            ['code' => 'USD', 'name' => 'Dollar américain', 'rate' => 1.35],
            ['code' => 'EUR', 'name' => 'Euro', 'rate' => 1.55],
        ] as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
