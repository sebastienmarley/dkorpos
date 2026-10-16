<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WeekTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ensemble de quarts type formant une semaine (au plus un par jour), appliqué à un employé à la fois.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class WeekTemplate extends Model
{
    /** @use HasFactory<WeekTemplateFactory> */
    use HasFactory;

    /** @return HasMany<WeekTemplateEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WeekTemplateEntry::class);
    }

    /**
     * Quarts à créer pour un employé, la semaine commençant le dimanche $weekStart.
     *
     * @return Collection<int, array{user_id: int, date: string, start_time: string, end_time: string, break_minutes: int}>
     */
    public function plannedShiftsFor(User $employee, CarbonInterface $weekStart): Collection
    {
        return $this->entries()
            ->with('shiftTemplate')
            ->orderBy('weekday')
            ->get()
            ->map(fn (WeekTemplateEntry $entry): array => [
                'user_id' => $employee->id,
                'date' => $weekStart->copy()->addDays($entry->weekday)->toDateString(),
                'start_time' => $entry->shiftTemplate->start_time,
                'end_time' => $entry->shiftTemplate->end_time,
                'break_minutes' => $entry->shiftTemplate->break_minutes,
            ]);
    }
}
