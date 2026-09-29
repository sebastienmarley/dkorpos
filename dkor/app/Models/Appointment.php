<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $customer_id
 * @property string $date
 * @property int $start_minute
 * @property int $duration_minutes
 * @property string $title
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'customer_id', 'date', 'start_minute', 'duration_minutes', 'title', 'notes', 'created_by', 'last_updated_by'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $authorId = auth()->user()?->id;

            $appointment->created_by ??= $authorId;
            $appointment->last_updated_by ??= $authorId;
        });

        static::updating(function (Appointment $appointment): void {
            $authorId = auth()->user()?->id;

            if ($authorId !== null) {
                $appointment->last_updated_by = $authorId;
            }
        });
    }

    public function endMinute(): int
    {
        return $this->start_minute + $this->duration_minutes;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(customer::class);
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
