<?php

namespace App\Actions;

use App\Enums\ScheduleStatus;
use App\Models\PayrollPeriod;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Verrouille ou déverrouille les horaires d'une période de paie.
 *
 * Au verrouillage, les quarts brouillons et publiés de la période passent au statut Fermée (non modifiable).
 * Au déverrouillage, les quarts fermés redeviennent publiés.
 */
class LockPayrollPeriod
{
    public function lock(CarbonInterface $start, User $actor): PayrollPeriod
    {
        $start = PayrollPeriod::startFor($start);
        $end = PayrollPeriod::endFor($start);

        return DB::transaction(function () use ($start, $end, $actor): PayrollPeriod {
            $period = PayrollPeriod::query()->firstOrCreate(
                ['start_date' => $start->toDateString()],
                ['end_date' => $end->toDateString(), 'locked_at' => now(), 'locked_by' => $actor->id],
            );

            $this->schedulesBetween($start, $end)
                ->whereIn('status', array_map(fn (ScheduleStatus $status) => $status->value, ScheduleStatus::editableValues()))
                ->update(['status' => ScheduleStatus::Closed->value, 'last_updated_by' => $actor->id]);

            return $period;
        });
    }

    public function unlock(CarbonInterface $start, User $actor): void
    {
        $start = PayrollPeriod::startFor($start);
        $end = PayrollPeriod::endFor($start);

        DB::transaction(function () use ($start, $end, $actor): void {
            PayrollPeriod::query()->whereDate('start_date', $start->toDateString())->delete();

            $this->schedulesBetween($start, $end)
                ->where('status', ScheduleStatus::Closed->value)
                ->update(['status' => ScheduleStatus::Published->value, 'last_updated_by' => $actor->id]);
        });
    }

    /**
     * @return Builder<Schedule>
     */
    private function schedulesBetween(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Schedule::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());
    }
}
