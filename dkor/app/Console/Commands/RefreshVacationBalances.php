<?php

namespace App\Console\Commands;

use App\Actions\CalculateVacationBalance;
use App\Models\User;
use Illuminate\Console\Command;

class RefreshVacationBalances extends Command
{
    protected $signature = 'vacations:refresh';

    protected $description = 'Recalcule le solde de vacances des employés actifs (acquisition quotidienne)';

    public function handle(CalculateVacationBalance $calculator): int
    {
        $count = 0;

        User::query()->where('is_active', true)->each(function (User $user) use ($calculator, &$count): void {
            $calculator->refresh($user);
            $count++;
        });

        $this->info("Soldes de vacances recalculés : {$count}");

        return self::SUCCESS;
    }
}
