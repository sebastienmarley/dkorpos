<?php

namespace App\Models;

use Database\Factories\WeekTemplateEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $week_template_id
 * @property int $user_id
 * @property int $shift_template_id
 * @property int $weekday 0 = dimanche … 6 = samedi (comme la grille des horaires)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['week_template_id', 'user_id', 'shift_template_id', 'weekday'])]
class WeekTemplateEntry extends Model
{
    /** @use HasFactory<WeekTemplateEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<WeekTemplate, $this> */
    public function weekTemplate(): BelongsTo
    {
        return $this->belongsTo(WeekTemplate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ShiftTemplate, $this> */
    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
}
