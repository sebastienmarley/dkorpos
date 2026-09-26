<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $break_minutes
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'break_minutes', 'notes'])]
class Schedule extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
