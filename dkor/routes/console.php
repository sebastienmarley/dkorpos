<?php

use App\Console\Commands\ApplyPriceLists;
use App\Console\Commands\ArchiveExpiredPriceLists;
use App\Console\Commands\DeactivateExpiredEmployees;
use App\Console\Commands\PurgeDeliverySignatures;
use App\Console\Commands\QueuePriceChangeLabels;
use App\Console\Commands\RefreshVacationBalances;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DeactivateExpiredEmployees::class)->dailyAt('00:01');
Schedule::command(RefreshVacationBalances::class)->dailyAt('00:05');
Schedule::command(ArchiveExpiredPriceLists::class)->dailyAt('04:00');
Schedule::command(ApplyPriceLists::class)->dailyAt('05:00');
Schedule::command(QueuePriceChangeLabels::class)->dailyAt('05:30');
Schedule::command(PurgeDeliverySignatures::class)->dailyAt('03:00');
