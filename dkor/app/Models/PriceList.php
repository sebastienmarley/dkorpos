<?php

namespace App\Models;

use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 */
#[Fillable(['supplier_id', 'starts_on', 'ends_on', 'archived_at'])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PriceListList, $this> */
    public function lists(): HasMany
    {
        return $this->hasMany(PriceListList::class);
    }

    /**
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isActive(): bool
    {
        return $this->archived_at === null;
    }

    public static function defaultEndDate(): Carbon
    {
        return Carbon::today()->addYears(5)->endOfYear()->startOfDay();
    }

    public static function supplierHasActiveList(int $supplierId, ?int $exceptId = null): bool
    {
        return static::query()
            ->active()
            ->where('supplier_id', $supplierId)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
