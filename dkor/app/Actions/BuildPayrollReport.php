<?php

namespace App\Actions;

use App\Enums\ScheduleType;
use App\Models\PayrollPeriod;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rapport de paie d'une période de 2 semaines, une ligne par employé.
 *
 * - Heures travaillées et de formation : durée des quarts, moins la pause non payée.
 * - Maladie payée : chaque journée Maladie vaut les heures par jour de l'employé, jusqu'au maximum de jours
 *   de son magasin (temps plein ou temps partiel) pour l'année de maladie (début MM-JJ du magasin).
 * - Vacances à payer : chaque journée Vacances vaut les heures par jour de l'employé.
 * - Primes et commissions : à 0 tant qu'aucun module de ventes n'existe.
 */
class BuildPayrollReport
{
    /**
     * @return Collection<int, array{user: User, worked_hours: float, sick_paid_hours: float, training_hours: float, vacation_hours: float, bonus: float, commission: float}>
     */
    public function handle(CarbonInterface $periodStart): Collection
    {
        $start = PayrollPeriod::startFor($periodStart);
        $end = PayrollPeriod::endFor($start);

        $schedules = Schedule::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->groupBy('user_id');

        $users = User::query()
            ->with('store')
            ->where(fn ($query) => $query
                ->whereIn('id', $schedules->keys())
                ->orWhere(fn ($active) => $active
                    ->where('is_active', true)
                    ->whereDate('first_day', '<=', $end->toDateString())
                    ->where(fn ($q) => $q->whereNull('last_day')->orWhereDate('last_day', '>=', $start->toDateString()))))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        return $users
            ->map(fn (User $user) => $this->row($user, $schedules->get($user->id, collect()), $start, $end))
            ->values();
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return array{user: User, worked_hours: float, sick_paid_hours: float, training_hours: float, vacation_hours: float, bonus: float, commission: float}
     */
    private function row(User $user, Collection $schedules, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $hoursPerDay = (float) ($user->hours_per_day ?? config('vacations.default_hours_per_day'));

        return [
            'user' => $user,
            'worked_hours' => $this->scheduledHours($schedules, ScheduleType::Work),
            'sick_paid_hours' => round($this->paidSickDays($user, $start, $end) * $hoursPerDay, 2),
            'training_hours' => $this->scheduledHours($schedules, ScheduleType::Training),
            'vacation_hours' => round($schedules->where('type', ScheduleType::Vacation)->count() * $hoursPerDay, 2),
            // Primes et commissions dépendent des ventes : 0 tant qu'aucun module de ventes n'existe.
            'bonus' => 0.0,
            'commission' => 0.0,
        ];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function scheduledHours(Collection $schedules, ScheduleType $type): float
    {
        $minutes = $schedules
            ->where('type', $type)
            ->filter(fn (Schedule $schedule) => $schedule->start_time && $schedule->end_time)
            ->sum(fn (Schedule $schedule) => max(0, $this->minutesOf((string) $schedule->end_time) - $this->minutesOf((string) $schedule->start_time) - $schedule->break_minutes));

        return round($minutes / 60, 2);
    }

    /**
     * Journées Maladie de la période qui restent dans le maximum payé de l'année de maladie.
     */
    private function paidSickDays(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $maximum = $user->is_full_time ? $user->store?->sick_days_full_time : $user->store?->sick_days_part_time;

        if (! $maximum) {
            return 0;
        }

        [$month, $day] = CalculateVacationBalance::monthDay(
            $user->store->sick_accrual_start,
            ...array_map('intval', explode('-', (string) config('payroll.default_sick_accrual_start'))),
        );

        $firstYearStart = CalculateVacationBalance::yearStartContaining($start, $month, $day);

        /** @var list<string> $sickDates */
        $sickDates = $user->schedules()
            ->where('type', ScheduleType::Sick->value)
            ->whereDate('date', '>=', $firstYearStart->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->pluck('date')
            ->map(fn (mixed $value) => CarbonImmutable::parse($value)->toDateString())
            ->all();

        $paid = 0;

        foreach ($sickDates as $sickDate) {
            if ($sickDate < $start->toDateString()) {
                continue;
            }

            $yearStart = CalculateVacationBalance::yearStartContaining(CarbonImmutable::parse($sickDate), $month, $day)->toDateString();
            $alreadyTaken = count(array_filter($sickDates, fn (string $d) => $d >= $yearStart && $d < $sickDate));

            if ($alreadyTaken < $maximum) {
                $paid++;
            }
        }

        return $paid;
    }

    private function minutesOf(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
