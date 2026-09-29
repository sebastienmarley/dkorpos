<?php

namespace App\Models;

use Database\Factories\ShiftTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $break_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'start_time', 'end_time', 'break_minutes'])]
class ShiftTemplate extends Model
{
    /** @use HasFactory<ShiftTemplateFactory> */
    use HasFactory;

    /** @return HasMany<WeekTemplateEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WeekTemplateEntry::class);
    }

    /** Ex. « Ouverture · 08:00–16:00 ». */
    public function summary(): string
    {
        return $this->name.' · '.substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }
}
