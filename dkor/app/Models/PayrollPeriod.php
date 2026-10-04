<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Période de paie de 2 semaines verrouillée : ses horaires ne peuvent plus être modifiés.
 *
 * Les périodes s'enchaînent à partir de config('payroll.period_anchor') ; seules les périodes
 * verrouillées existent en base.
 *
 * @property int $id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon $locked_at
 * @property int|null $locked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['start_date', 'end_date', 'locked_at', 'locked_by'])]
class PayrollPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Premier jour de la période de paie qui contient la date.
     */
    public static function startFor(CarbonInterface|string $date): CarbonImmutable
    {
        $anchor = CarbonImmutable::parse(config('payroll.period_anchor'))->startOfDay();
        $days = (int) config('payroll.period_days');
        $offset = (int) $anchor->diffInDays(CarbonImmutable::parse($date)->startOfDay(), false);

        return $anchor->addDays((int) floor($offset / $days) * $days);
    }

    public static function endFor(CarbonInterface|string $start): CarbonImmutable
    {
        return CarbonImmutable::parse($start)->startOfDay()->addDays((int) config('payroll.period_days') - 1);
    }

    /**
     * Indique si la date tombe dans une période de paie verrouillée.
     */
    public static function isDateLocked(CarbonInterface|string $date): bool
    {
        $day = CarbonImmutable::parse($date)->toDateString();

        return static::query()->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->exists();
    }
}
