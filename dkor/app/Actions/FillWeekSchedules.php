<?php

namespace App\Actions;

use App\Enums\ScheduleStatus;
use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crée en brouillon les quarts proposés (copie de semaine ou semaine type).
 *
 * Ne remplace jamais un quart existant, quel que soit son statut, et ignore les cases où l'employé
 * n'est pas en fonction, est inactif, ou a déjà des rendez-vous qui ne tiendraient pas dans le quart.
 */
class FillWeekSchedules
{
    /**
     * @param  iterable<array{user_id: int, date: string, start_time: string, end_time: string, break_minutes: int, notes?: string|null}>  $plannedShifts
     * @return array{created: int, existing: int, unavailable: int, conflicts: int}
     */
    public function handle(iterable $plannedShifts): array
    {
        $result = ['created' => 0, 'existing' => 0, 'unavailable' => 0, 'conflicts' => 0];

        foreach (collect($plannedShifts)->groupBy('user_id') as $userId => $shifts) {
            $employee = User::query()->find($userId);

            DB::transaction(function () use ($userId, $shifts, $employee, &$result): void {
                User::query()->whereKey($userId)->lockForUpdate()->first();

                foreach ($shifts as $shift) {
                    $outcome = $this->fill($employee, $shift);
                    $result[$outcome]++;
                }
            });
        }

        return $result;
    }

    /**
     * @param  array{user_id: int, date: string, start_time: string, end_time: string, break_minutes: int, notes?: string|null}  $shift
     * @return 'created'|'existing'|'unavailable'|'conflicts'
     */
    private function fill(?User $employee, array $shift): string
    {
        if (! $employee || ! $employee->is_active || ! $this->isInService($employee, $shift['date'])) {
            return 'unavailable';
        }

        $exists = Schedule::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', $shift['date'])
            ->exists();

        if ($exists) {
            return 'existing';
        }

        $window = [$this->minutesOf($shift['start_time']), $this->minutesOf($shift['end_time'])];

        if (Appointment::countOutsideWindow($employee->id, $shift['date'], $window) > 0) {
            return 'conflicts';
        }

        Schedule::create([
            'user_id' => $employee->id,
            'date' => $shift['date'],
            'start_time' => $shift['start_time'],
            'end_time' => $shift['end_time'],
            'break_minutes' => $shift['break_minutes'],
            'status' => ScheduleStatus::Draft,
            'notes' => $shift['notes'] ?? null,
        ]);

        return 'created';
    }

    private function isInService(User $employee, string $date): bool
    {
        if (! $employee->first_day || $date < $employee->first_day->toDateString()) {
            return false;
        }

        return ! ($employee->last_day && $date > $employee->last_day->toDateString());
    }

    private function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return $parsed->hour * 60 + $parsed->minute;
    }
}
