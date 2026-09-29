<?php

namespace App\Models;

use Database\Factories\WeekTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
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
}
