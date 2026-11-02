<?php

namespace App\Actions;

use App\Enums\ScheduleType;
use App\Models\PayrollPeriod;
use App\Models\Schedule;
use App\Models\User;
use App\Support\SalesReport;
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
 * - Ventes d'un vendeur : sa part des ventes de ses commandes (SalesReport), au moment où l'article est remis au
 *   client, avant taxes, moins les retours.
 * - Commission : taux de l'employé sur ses ventes de la période.
 * - Primes : chaque semaine de la période, la prime de l'employé pour chaque tranche complète de ventes au-delà de
 *   son objectif hebdomadaire.
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

        $weeklySales = [];

        for ($weekStart = $start; $weekStart->lte($end); $weekStart = $weekStart->addWeek()) {
            $weeklySales[] = SalesReport::bySalesperson($weekStart, $weekStart->addDays(6)->min($end));
        }

        return $users
            ->map(fn (User $user) => $this->row($user, $schedules->get($user->id, collect()), $start, $end, $weeklySales))
            ->values();
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @param  list<array<int, int>>  $weeklySales  ventes en cents par vendeur, une entrée par semaine de la période
     * @return array{user: User, worked_hours: float, sick_paid_hours: float, training_hours: float, vacation_hours: float, bonus: float, commission: float}
     */
    private function row(User $user, Collection $schedules, CarbonImmutable $start, CarbonImmutable $end, array $weeklySales): array
    {
        $hoursPerDay = (float) ($user->hours_per_day ?? config('vacations.default_hours_per_day'));

        return [
            'user' => $user,
            'worked_hours' => $this->scheduledHours($schedules, ScheduleType::Work),
            'sick_paid_hours' => round($this->paidSickDays($user, $start, $end) * $hoursPerDay, 2),
            'training_hours' => $this->scheduledHours($schedules, ScheduleType::Training),
            'vacation_hours' => round($schedules->where('type', ScheduleType::Vacation)->count() * $hoursPerDay, 2),
            'bonus' => array_sum(array_map(fn (array $sales): float => $this->weeklyBonus($user, $sales[$user->id] ?? 0), $weeklySales)),
            'commission' => $this->commission($user, array_sum(array_map(fn (array $sales): int => $sales[$user->id] ?? 0, $weeklySales))),
        ];
    }

    private function commission(User $user, int $salesCents): float
    {
        if (! $user->has_commission || $user->commission_rate === null || $salesCents <= 0) {
            return 0.0;
        }

        return round($salesCents * (float) $user->commission_rate / 100 / 100, 2);
    }

    private function weeklyBonus(User $user, int $salesCents): float
    {
        if (! $user->has_bonus || ! $user->weekly_sales_target || ! $user->bonus_amount || ! $user->bonus_step) {
            return 0.0;
        }

        $tranches = intdiv(max(0, $salesCents - $user->weekly_sales_target * 100), $user->bonus_step * 100);

        return (float) ($tranches * $user->bonus_amount);
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
