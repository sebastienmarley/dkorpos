<?php

namespace App\Console\Commands;
/*Pour qu'elle s'exécute en production, le cron de Laravel doit être actif 
(* * * * * php artisan schedule:run). */

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DeactivateExpiredEmployees extends Command
{
    protected $signature = 'employees:deactivate-expired';

    protected $description = 'Désactive les employés dont le dernier jour de travail est passé';

    public function handle(): int
    {
        $today = Carbon::today();

        $count = 0;

        User::query()
            ->where('is_active', true)
            ->whereNotNull('last_day')
            ->whereDate('last_day', '<', $today)
            ->each(function (User $user) use (&$count): void {
                $user->forceFill(['is_active' => false])->save();
                $count++;
            });

        $this->info("Employés désactivés : {$count}");

        return self::SUCCESS;
    }
}
