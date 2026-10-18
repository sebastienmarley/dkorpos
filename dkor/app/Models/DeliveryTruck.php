<?php

namespace App\Models;

use Database\Factories\DeliveryTruckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Camion de livraison d'un magasin : il peut faire `capacity_per_period` livraisons par demi-journée, avec un livreur.
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property int $capacity_per_period
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Store $store
 */
#[Fillable(['store_id', 'name', 'capacity_per_period', 'is_active'])]
class DeliveryTruck extends Model
{
    /** @use HasFactory<DeliveryTruckFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'capacity_per_period' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
