<?php

namespace App\Actions;

use App\Enums\ScheduleType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calcule le solde de vacances d'un employé selon la Loi sur les normes du travail du Québec.
 *
 * - Année de référence commune (config vacations.reference_year_start) : le cumul repart à chaque début d'année.
 * - Droit annuel selon l'ancienneté à la date du calcul : moins d'un an = 1 jour par mois de service
 *   (maximum 10), 1 an et plus = 10 jours, 3 ans et plus = 15 jours.
 * - Acquisition progressive au prorata des jours écoulés : prendre ses vacances tôt dans l'année
 *   donne un solde d'heures négatif (« emprunt ») qui se résorbe au fil de l'année.
 * - Chaque journée Vacances à l'horaire retire les heures par jour de l'employé.
 * - Un solde négatif à la fin d'une année de référence est reporté sur la suivante (un solde positif ne l'est pas).
 */
class CalculateVacationBalance
{
    /**
     * @return array{reference_start: CarbonImmutable, days_accrued: float, hours_accrued: float, days_taken: int, hours_taken: float, hours_carried_over: float, hours_available: float, hours_per_day: float}
     */
    public function calculate(User $user, ?CarbonInterface $date = null): array
    {
        $date = CarbonImmutable::parse($date ?? now())->startOfDay();
        $referenceStart = $this->referenceYearStart($date);
        $hoursPerDay = (float) ($user->vacation_hours_per_day ?? config('vacations.default_hours_per_day'));

        /** @var list<string> $vacationDates */
        $vacationDates = $user->schedules()
            ->where('type', ScheduleType::Vacation->value)
            ->whereDate('date', '<=', $date->toDateString())
            ->pluck('date')
            ->map(fn (mixed $value) => CarbonImmutable::parse($value)->toDateString())
            ->all();

        $hoursCarriedOver = $this->negativeCarryOver($user, $referenceStart, $vacationDates, $hoursPerDay);

        $daysAccrued = $this->daysAccrued($user, $date, $referenceStart, $referenceStart->addYear());
        $daysTaken = $this->daysTaken($vacationDates, $referenceStart, $date);

        $hoursAccrued = round($daysAccrued * $hoursPerDay, 2);
        $hoursTaken = round($daysTaken * $hoursPerDay, 2);

        return [
            'reference_start' => $referenceStart,
            'days_accrued' => $daysAccrued,
            'hours_accrued' => $hoursAccrued,
            'days_taken' => $daysTaken,
            'hours_taken' => $hoursTaken,
            'hours_carried_over' => $hoursCarriedOver,
            'hours_available' => round($hoursAccrued - $hoursTaken + $hoursCarriedOver, 2),
            'hours_per_day' => $hoursPerDay,
        ];
    }

    /**
     * Recalcule et enregistre le solde (sans toucher aux champs d'audit de la fiche).
     */
    public function refresh(User $user, ?CarbonInterface $date = null): void
    {
        $balance = $this->calculate($user, $date);

        $user->forceFill([
            'vacation_days_accrued' => $balance['days_accrued'],
            'vacation_hours_available' => $balance['hours_available'],
            'vacation_balance_computed_at' => now(),
        ])->saveQuietly();
    }

    public function referenceYearStart(CarbonInterface $date): CarbonImmutable
    {
        $start = CarbonImmutable::create(
            $date->year,
            (int) config('vacations.reference_year_start.month'),
            (int) config('vacations.reference_year_start.day'),
        )->startOfDay();

        return $start->greaterThan($date) ? $start->subYear() : $start;
    }

    /**
     * Solde négatif hérité des années de référence précédentes, année par année depuis l'embauche :
     * chaque année se termine avec son droit complet, moins les vacances prises, plus le report reçu ;
     * seul un résultat négatif passe à l'année suivante.
     *
     * @param  list<string>  $vacationDates
     */
    private function negativeCarryOver(User $user, CarbonImmutable $currentReferenceStart, array $vacationDates, float $hoursPerDay): float
    {
        if ($user->first_day === null) {
            return 0.0;
        }

        $carry = 0.0;
        $yearStart = $this->referenceYearStart(CarbonImmutable::parse($user->first_day));

        while ($yearStart->lessThan($currentReferenceStart)) {
            $yearEnd = $yearStart->addYear();
            $lastDayOfYear = $yearEnd->subDay();

            $balance = $this->daysAccrued($user, $lastDayOfYear, $yearStart, $yearEnd) * $hoursPerDay
                - $this->daysTaken($vacationDates, $yearStart, $lastDayOfYear) * $hoursPerDay
                + $carry;

            $carry = round(min(0.0, $balance), 2);
            $yearStart = $yearEnd;
        }

        return $carry;
    }

    /**
     * @param  list<string>  $vacationDates
     */
    private function daysTaken(array $vacationDates, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $fromDate = $from->toDateString();
        $untilDate = $until->toDateString();

        return count(array_filter($vacationDates, fn (string $day) => $day >= $fromDate && $day <= $untilDate));
    }

    private function daysAccrued(User $user, CarbonImmutable $date, CarbonImmutable $referenceStart, CarbonImmutable $referenceEnd): float
    {
        if ($user->first_day === null) {
            return 0.0;
        }

        $firstDay = CarbonImmutable::parse($user->first_day)->startOfDay();

        // Après le départ, l'acquisition s'arrête au dernier jour.
        if ($user->last_day !== null) {
            $date = $date->min(CarbonImmutable::parse($user->last_day)->startOfDay());
        }

        if ($firstDay->greaterThan($date) || $date->lessThan($referenceStart)) {
            return 0.0;
        }

        $accrualStart = $firstDay->max($referenceStart);
        $daysInYear = (int) $referenceStart->diffInDays($referenceEnd);
        $elapsedDays = (int) $accrualStart->diffInDays($date->addDay());
        $seniorityYears = (int) floor($firstDay->diffInYears($date));

        foreach (config('vacations.tiers') as $minimumYears => $annualDays) {
            if ($seniorityYears >= $minimumYears) {
                return round($annualDays * $elapsedDays / $daysInYear, 2);
            }
        }

        // Moins d'un an de service : 1 jour par mois, au prorata, plafonné.
        return round(min((float) config('vacations.first_year_max_days'), $elapsedDays * 12 / $daysInYear), 2);
    }
}
