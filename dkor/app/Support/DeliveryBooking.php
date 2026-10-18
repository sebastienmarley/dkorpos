<?php

namespace App\Support;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryPeriod;
use App\Models\CustomerOrderDelivery;
use App\Models\DeliveryTruck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Règles de planification des livraisons par nos camions, par demi-journée (AM, PM) et par magasin.
 *
 * - Un livreur est disponible pendant une demi-journée si son quart publié la chevauche, sans absence ni férié
 *   fermé. Sans quart publié, il l'est après la semaine en cours jusqu'à l'horizon des rendez-vous (comme
 *   AppointmentBooking).
 * - Chaque camion actif part avec un livreur disponible : les plus gros camions partent en premier. La capacité de
 *   la demi-journée est la somme des livraisons que peuvent faire ces camions.
 * - Une demi-journée commencée ne se planifie plus.
 */
class DeliveryBooking
{
    /** @return Collection<int, User> */
    public static function driversOf(int $storeId): Collection
    {
        return User::role('delivery')->where('is_active', true)->where('store_id', $storeId)->get();
    }

    public static function isDriverAvailable(User $driver, string $date, DeliveryPeriod $period): bool
    {
        if (AppointmentBooking::blockReasonFor($driver->id, $date) !== null) {
            return false;
        }

        $window = AppointmentBooking::workingWindow($driver->id, $date);

        if ($window !== null) {
            return $window[0] < $period->endMinute() && $window[1] > $period->startMinute();
        }

        return AppointmentBooking::allowsUnscheduledBooking($date);
    }

    /** Nombre de livraisons que le magasin peut faire pendant cette demi-journée. */
    public static function capacity(int $storeId, string $date, DeliveryPeriod $period): int
    {
        $crews = self::driversOf($storeId)
            ->filter(fn (User $driver): bool => self::isDriverAvailable($driver, $date, $period))
            ->count();

        if ($crews === 0) {
            return 0;
        }

        return (int) DeliveryTruck::query()
            ->active()
            ->where('store_id', $storeId)
            ->orderByDesc('capacity_per_period')
            ->limit($crews)
            ->pluck('capacity_per_period')
            ->sum();
    }

    /** Livraisons actives déjà planifiées pendant cette demi-journée. */
    public static function bookedCount(int $storeId, string $date, DeliveryPeriod $period, ?int $ignoringDeliveryId = null): int
    {
        return CustomerOrderDelivery::query()
            ->active()
            ->where('method', DeliveryMethod::Internal)
            ->where('store_id', $storeId)
            ->whereDate('scheduled_date', $date)
            ->where('period', $period)
            ->when($ignoringDeliveryId !== null, fn ($query) => $query->whereKeyNot($ignoringDeliveryId))
            ->count();
    }

    public static function remaining(int $storeId, string $date, DeliveryPeriod $period, ?int $ignoringDeliveryId = null): int
    {
        return max(0, self::capacity($storeId, $date, $period) - self::bookedCount($storeId, $date, $period, $ignoringDeliveryId));
    }

    public static function hasStarted(string $date, DeliveryPeriod $period): bool
    {
        return Carbon::parse($date)->startOfDay()->addMinutes($period->startMinute())->lte(Carbon::now());
    }

    public static function canBook(int $storeId, string $date, DeliveryPeriod $period, ?int $ignoringDeliveryId = null): bool
    {
        return ! self::hasStarted($date, $period) && self::remaining($storeId, $date, $period, $ignoringDeliveryId) > 0;
    }

    /**
     * Places de chaque demi-journée sur plusieurs jours, pour le choix de la date et la grille des livraisons.
     *
     * @return array<string, array<string, array{capacity: int, booked: int, started: bool}>> par date, puis par période
     */
    public static function slots(int $storeId, string $from, int $days): array
    {
        $slots = [];

        for ($day = 0; $day < $days; $day++) {
            $date = Carbon::parse($from)->addDays($day)->toDateString();

            foreach (DeliveryPeriod::cases() as $period) {
                $slots[$date][$period->value] = [
                    'capacity' => self::capacity($storeId, $date, $period),
                    'booked' => self::bookedCount($storeId, $date, $period),
                    'started' => self::hasStarted($date, $period),
                ];
            }
        }

        return $slots;
    }
}
