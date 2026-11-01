<?php

use App\Models\CustomerOrder;
use App\Models\SupplierInvoice;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;

it('crée des données de démonstration dont le grand livre concorde avec les documents', function () {
    $this->seed([DatabaseSeeder::class, DemoDataSeeder::class]);

    expect(CustomerOrder::count())->toBeGreaterThan(20)
        ->and(SupplierInvoice::count())->toBeGreaterThan(5)
        ->and(now()->isToday())->toBeTrue();

    $this->artisan('ledger:check')->expectsOutputToContain('concorde avec les documents')->assertSuccessful();
});
