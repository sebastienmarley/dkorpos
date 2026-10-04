<?php

namespace App\Models;

use App\Actions\CalculateVacationBalance;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleType;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property ScheduleStatus $status
 * @property ScheduleType $type
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'break_minutes', 'status', 'type', 'notes', 'created_by', 'last_updated_by'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Schedule $schedule): void {
            $authorId = auth()->user()?->id;

            $schedule->created_by ??= $authorId;
            $schedule->last_updated_by ??= $authorId;
        });

        // Une journée de vacances ajoutée, déplacée ou retirée change le solde de l'employé.
        $refreshVacationBalance = function (Schedule $schedule): void {
            $wasVacation = $schedule->getOriginal('type') === ScheduleType::Vacation
                || $schedule->getOriginal('type') === ScheduleType::Vacation->value;

            if ($schedule->type !== ScheduleType::Vacation && ! $wasVacation) {
                return;
            }

            $userIds = array_unique(array_filter([$schedule->user_id, $schedule->getOriginal('user_id')]));

            User::query()->whereKey($userIds)->get()
                ->each(fn (User $user) => app(CalculateVacationBalance::class)->refresh($user));
        };

        static::saved($refreshVacationBalance);
        static::deleted($refreshVacationBalance);

        static::updating(function (Schedule $schedule): void {
            $authorId = auth()->user()?->id;

            if ($authorId !== null) {
                $schedule->last_updated_by = $authorId;
            }
        });
    }

    /**
     * Quarts visibles pour la prise de rendez-vous : les brouillons ne comptent pas.
     *
     * @param  Builder<Schedule>  $query
     * @return Builder<Schedule>
     */
    #[Scope]
    protected function bookable(Builder $query): Builder
    {
        return $query->where('status', '!=', ScheduleStatus::Draft->value);
    }

    protected function casts(): array
    {
        return [
            'status' => ScheduleStatus::class,
            'type' => ScheduleType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
