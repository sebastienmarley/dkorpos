<?php

namespace App\Models;

use Database\Factories\PriceListListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_list_id
 * @property string $name
 * @property float $discount_percent
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceList $priceList
 */
#[Fillable(['price_list_id', 'name', 'discount_percent', 'applied_at'])]
class PriceListList extends Model
{
    /** @use HasFactory<PriceListListFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['discount_percent' => 'float', 'applied_at' => 'datetime'];
    }

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return HasMany<PriceListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
