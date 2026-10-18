<?php

namespace App\Models;

use Database\Factories\InventoryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zone permanente de l'entrepôt (ex. « Allée 1 ») : le stock d'une prise d'inventaire se compte zone par zone, chaque
 * zone par deux compteurs (ADR 0011).
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'sort_order', 'is_active'])]
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
