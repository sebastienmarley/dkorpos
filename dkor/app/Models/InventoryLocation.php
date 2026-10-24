<?php

namespace App\Models;

use Database\Factories\InventoryLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Emplacement précis dans une zone de l'entrepôt d'un magasin (ex. : A-03-2), unique par magasin (issue #96). Il dit
 * où chercher un article : il ne tient pas de quantités. Désactivé, il n'est plus proposé mais reste affiché là où il
 * est noté.
 *
 * @property int $id
 * @property int $inventory_zone_id
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryZone $zone
 */
#[Fillable(['inventory_zone_id', 'code', 'description', 'is_active'])]
class InventoryLocation extends Model
{
    /** @use HasFactory<InventoryLocationFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<InventoryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(InventoryZone::class, 'inventory_zone_id');
    }

    /**
     * Emplacements d'un magasin (par sa zone).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function ofStore(Builder $query, int $storeId): Builder
    {
        return $query->whereHas('zone', fn (Builder $zone) => $zone->where('store_id', $storeId));
    }

    /**
     * Emplacements proposés : actifs, dans une zone active.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function selectable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('zone', fn (Builder $zone) => $zone->where('is_active', true));
    }

    /** Code suivi de la zone (ex. : A-03-2 · Allée A). */
    public function label(): string
    {
        return $this->code.' · '.$this->zone->name;
    }

    /** Emplacement actif d'un magasin dont le code correspond au code balayé (sans tenir compte de la casse). */
    public static function findByCode(int $storeId, string $code): ?self
    {
        return static::query()->ofStore($storeId)->selectable()->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])->first();
    }
}
