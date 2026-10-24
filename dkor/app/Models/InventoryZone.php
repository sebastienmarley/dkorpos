<?php

namespace App\Models;

use Database\Factories\InventoryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Zone permanente de l'entrepôt d'un magasin (ex. « Allée 1 ») : le stock d'une prise d'inventaire se compte zone par
 * zone, chaque zone par deux compteurs (ADR 0011). Une zone regroupe des emplacements (issue #96).
 *
 * @property int $id
 * @property int|null $store_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Store|null $store
 * @property-read Collection<int, InventoryLocation> $locations
 */
#[Fillable(['store_id', 'name', 'sort_order', 'is_active'])]
class InventoryZone extends Model
{
    /** @use HasFactory<InventoryZoneFactory> */
    use HasFactory;

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<InventoryLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class)->orderBy('code');
    }

    /** Nom affiché avec le magasin (ex. : « Rimouski — Allée 1 »), pour la prise d'inventaire de l'entreprise. */
    public function labelWithStore(): string
    {
        return $this->store === null ? $this->name : $this->store->name.' — '.$this->name;
    }

    /** @param Builder<InventoryZone> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<InventoryZone> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
