<?php

namespace App\Actions;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Crée les jours fériés légaux du Québec pour une année, sans toucher aux dates déjà enregistrées.
 */
class GenerateQuebecHolidays
{
    /**
     * @return int Nombre de fériés créés.
     */
    public function handle(int $year): int
    {
        $created = 0;

        foreach ($this->holidaysOf($year) as $date => $name) {
            $holiday = Holiday::query()->firstOrCreate(
                ['date' => $date],
                ['name' => $name, 'is_closed' => true],
            );

            $created += $holiday->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * @return array<string, string> Nom du férié, indexé par date (Y-m-d).
     */
    public function holidaysOf(int $year): array
    {
        $easter = $this->easterSunday($year);

        $holidays = [
            Carbon::create($year, 1, 1)->toDateString() => __('Jour de l\'An'),
            $easter->copy()->subDays(2)->toDateString() => __('Vendredi saint'),
            Carbon::create($year, 5, 25)->previous(Carbon::MONDAY)->toDateString() => __('Journée nationale des patriotes'),
            Carbon::create($year, 6, 24)->toDateString() => __('Fête nationale du Québec'),
            Carbon::create($year, 7, 1)->toDateString() => __('Fête du Canada'),
            Carbon::create($year, 9, 1)->nthOfMonth(1, Carbon::MONDAY)->toDateString() => __('Fête du travail'),
            Carbon::create($year, 10, 1)->nthOfMonth(2, Carbon::MONDAY)->toDateString() => __('Action de grâce'),
            Carbon::create($year, 12, 25)->toDateString() => __('Noël'),
        ];

        ksort($holidays);

        return $holidays;
    }

    /** Dimanche de Pâques (algorithme grégorien de Meeus/Jones/Butcher). */
    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
