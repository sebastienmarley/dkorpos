<?php

namespace App\Actions;

use App\Enums\ScheduleStatus;
use App\Models\User;

class PurgeSchedulesAfterLastDay
{
    /**
     * Delete all editable schedules after the user's last_day.
     *
     * @return int Number of deleted schedules
     */
    public function execute(User $user): int
    {
        if (! $user->last_day) {
            return 0;
        }

        return $user->schedules()
            ->whereDate('date', '>', $user->last_day)
            ->whereIn('status', ScheduleStatus::editableValues())
            ->delete();
    }
}
