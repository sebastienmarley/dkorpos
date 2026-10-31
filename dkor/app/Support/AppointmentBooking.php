<?php

namespace App\Support;

use App\Enums\AppointmentCoverage;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Règles de prise de rendez-vous, communes à la grille des rendez-vous et à la gestion des horaires.
 *
 * - Un rendez-vous est dans la grille (8 h à 19 h, par cases de 30 minutes) et dans le futur.
 * - Il tient dans les heures d'ouverture du magasin de l'employé (jamais un jour où le magasin est fermé).
 * - Jamais pendant une absence, ni un férié fermé où l'employé n'a pas de quart publié.
 * - Si l'employé a un quart publié ce jour-là, le rendez-vous tient dans ce quart.
 * - Sans quart publié, le rendez-vous est permis seulement après la semaine en cours et jusqu'à la fin de
 *   l'horizon (config `schedules.unscheduled_appointment_days`). Il devient alors une plage à couvrir.
 */
class AppointmentBooking
{
    /** Taille d'une case de la grille, en minutes. */
    public const SLOT_MINUTES = 30;

    public const DAY_START_MINUTE = 8 * 60;

    public const DAY_END_MINUTE = 19 * 60;

    /** Dernier jour où un rendez-vous peut être pris sans quart publié. */
    public static function unscheduledHorizonEnd(): Carbon
    {
        return Carbon::now()
            ->startOfWeek(Carbon::SUNDAY)
            ->addDays(6 + (int) config('schedules.unscheduled_appointment_days', 14))
            ->startOfDay();
    }

    /** Un rendez-vous peut-il être pris ce jour-là sans quart publié ? */
    public static function allowsUnscheduledBooking(string $date): bool
    {
        $currentWeekEnd = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays(6)->toDateString();

        return $date > $currentWeekEnd && $date <= self::unscheduledHorizonEnd()->toDateString();
    }

    public static function canBookAt(int $userId, string $date, int $startMinute): bool
    {
        if ($startMinute < self::DAY_START_MINUTE || $startMinute >= self::DAY_END_MINUTE) {
            return false;
        }

        if (Carbon::parse($date)->startOfDay()->addMinutes($startMinute)->lt(Carbon::now())) {
            return false;
        }

        $opening = self::openingWindow($userId, $date);

        if ($opening !== null && ($startMinute < $opening[0] || $startMinute >= $opening[1])) {
            return false;
        }

        if (self::blockReasonFor($userId, $date) !== null) {
            return false;
        }

        $window = self::workingWindow($userId, $date);

        if ($window !== null) {
            return $startMinute >= $window[0] && $startMinute < $window[1];
        }

        return self::allowsUnscheduledBooking($date);
    }

    /**
     * Heures d'ouverture du magasin de l'employé ce jour-là, en minutes depuis minuit.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function openingWindow(int $userId, string $date): ?array
    {
        return self::openingWindowOf(User::query()->with('store')->find($userId)?->store, $date);
    }

    /**
     * Heures d'ouverture d'un magasin un jour donné, en minutes depuis minuit : [0, 0] s'il est fermé ce jour-là,
     * null s'il n'a pas d'heures d'ouverture (aucune restriction).
     *
     * @return array{0: int, 1: int}|null
     */
    public static function openingWindowOf(?Store $store, string $date): ?array
    {
        if ($store?->opening_hours === null) {
            return null;
        }

        $hours = $store->opening_hours[strtolower(Carbon::parse($date)->englishDayOfWeek)] ?? null;

        if ($hours === null || ! $hours['open'] || blank($hours['from']) || blank($hours['to'])) {
            return [0, 0];
        }

        return [self::minutesOf($hours['from']), self::minutesOf($hours['to'])];
    }

    /**
     * Motif pour lequel l'employé ne prend pas de rendez-vous ce jour-là : une absence (maladie,
     * vacances…) ou un férié fermé sans quart de travail publié.
     */
    public static function blockReasonFor(int $userId, string $date): ?string
    {
        $absence = Schedule::query()
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->where('type', '!=', ScheduleType::Work->value)
            ->first();

        if ($absence) {
            return $absence->type->label();
        }

        $closedHoliday = Holiday::query()->whereDate('date', $date)->where('is_closed', true)->first();

        if (! $closedHoliday) {
            return null;
        }

        $worksThatDay = Schedule::query()
            ->bookable()
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->where('type', ScheduleType::Work->value)
            ->exists();

        return $worksThatDay ? null : $closedHoliday->name;
    }

    /**
     * Quart publié de l'employé pour une date, en minutes depuis minuit.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function workingWindow(int $userId, string $date): ?array
    {
        $schedule = Schedule::query()
            ->bookable()
            ->where('type', ScheduleType::Work->value)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        return $schedule ? self::windowOf($schedule) : null;
    }

    /**
     * Plage d'un quart en minutes depuis minuit, ou null s'il n'a pas d'heures.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function windowOf(Schedule $schedule): ?array
    {
        if (! $schedule->start_time || ! $schedule->end_time) {
            return null;
        }

        return [self::minutesOf($schedule->start_time), self::minutesOf($schedule->end_time)];
    }

    /**
     * Durée maximale réservable depuis $startMinute : limitée par la fin de journée, la fermeture du magasin, la fin
     * du quart publié et le prochain rendez-vous de l'employé.
     */
    public static function maxDurationMinutes(int $userId, string $date, int $startMinute, ?int $ignoreAppointmentId = null): int
    {
        $limit = self::DAY_END_MINUTE;

        $opening = self::openingWindow($userId, $date);

        if ($opening !== null) {
            if ($startMinute < $opening[0]) {
                return 0;
            }

            $limit = min($limit, $opening[1]);
        }

        $window = self::workingWindow($userId, $date);

        if ($window !== null) {
            $limit = min($limit, $window[1]);
        } elseif (! self::allowsUnscheduledBooking($date)) {
            return 0;
        }

        $others = Appointment::query()
            ->where('user_id', $userId)
            ->where('date', $date)
            ->when($ignoreAppointmentId, fn (Builder $query) => $query->where('id', '!=', $ignoreAppointmentId));

        $isStartTaken = (clone $others)
            ->where('start_minute', '<=', $startMinute)
            ->whereRaw('start_minute + duration_minutes > ?', [$startMinute])
            ->exists();

        if ($isStartTaken) {
            return 0;
        }

        $nextStart = (clone $others)->where('start_minute', '>', $startMinute)->min('start_minute');

        if ($nextStart !== null) {
            $limit = min($limit, (int) $nextStart);
        }

        return max(0, $limit - $startMinute);
    }

    /**
     * Couverture d'un rendez-vous par le quart de travail de son employé ce jour-là, ou null s'il est couvert
     * par un quart publié.
     */
    public static function coverageOf(Appointment $appointment, ?Schedule $workShift): ?AppointmentCoverage
    {
        $window = $workShift ? self::windowOf($workShift) : null;

        if ($window === null) {
            return AppointmentCoverage::NoShift;
        }

        if ($appointment->start_minute < $window[0] || $appointment->endMinute() > $window[1]) {
            return AppointmentCoverage::OutsideShift;
        }

        return $workShift->status === ScheduleStatus::Draft ? AppointmentCoverage::Unpublished : null;
    }

    /**
     * Rendez-vous à venir, entre deux dates incluses, qui ne sont pas couverts par un quart publié, limités aux
     * employés donnés s'il y a lieu (ceux d'un magasin).
     *
     * @param  array<int, int>|null  $userIds
     * @return Collection<int, array{appointment: Appointment, coverage: AppointmentCoverage}>
     */
    public static function uncoveredAppointments(string $from, string $to, ?array $userIds = null): Collection
    {
        $from = max($from, Carbon::today()->toDateString());
        $toExclusive = Carbon::parse($to)->addDay()->toDateString();

        $appointments = Appointment::query()
            ->where('date', '>=', $from)
            ->where('date', '<', $toExclusive)
            ->when($userIds !== null, fn (Builder $query) => $query->whereIn('user_id', $userIds))
            ->with(['user', 'customer'])
            ->orderBy('date')
            ->orderBy('start_minute')
            ->get();

        if ($appointments->isEmpty()) {
            return collect();
        }

        $workShifts = Schedule::query()
            ->where('type', ScheduleType::Work->value)
            ->whereIn('user_id', $appointments->pluck('user_id')->unique())
            ->where('date', '>=', $from)
            ->where('date', '<', $toExclusive)
            ->get()
            ->keyBy(fn (Schedule $schedule): string => $schedule->user_id.'|'.Carbon::parse($schedule->date)->toDateString());

        return $appointments
            ->map(fn (Appointment $appointment): array => [
                'appointment' => $appointment,
                'coverage' => self::coverageOf($appointment, $workShifts->get($appointment->user_id.'|'.$appointment->date)),
            ])
            ->filter(fn (array $row): bool => $row['coverage'] !== null)
            ->values();
    }

    /** Nombre de rendez-vous d'ici la fin de l'horizon qui attendent un quart publié. */
    public static function uncoveredCount(): int
    {
        return self::uncoveredAppointments(Carbon::today()->toDateString(), self::unscheduledHorizonEnd()->toDateString())->count();
    }

    public static function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return $parsed->hour * 60 + $parsed->minute;
    }

    public static function formatMinute(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
