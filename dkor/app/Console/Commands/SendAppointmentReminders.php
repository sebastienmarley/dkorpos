<?php

namespace App\Console\Commands;

use App\Actions\SendAppointmentReminders as SendAppointmentRemindersAction;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Rappelle aux clients leurs rendez-vous du lendemain';

    public function handle(SendAppointmentRemindersAction $sendReminders): int
    {
        $this->info('Rendez-vous rappelés : '.$sendReminders->handle());

        return self::SUCCESS;
    }
}
