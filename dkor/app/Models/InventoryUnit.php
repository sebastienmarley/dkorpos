<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\InventoryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Couche de coût FIFO (ADR 0011) : une unité possédée par le magasin, à son coût d'achat, sans état. Les unités en main
 * égalent la somme des états valorisés; seul InventoryMovement::record() les crée, les sort ou les rouvre.
 *
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property int $product_id
 * @property int|null $reception_line_id
 * @property Money $cost
 * @property Carbon $inserted_at
 * @property Carbon|null $delivered_at Date de sortie de l'inventaire (vente, perte…); vide tant que l'unité est en main.
 * @property int|null $exit_movement_id Mouvement qui a sorti l'unité.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read InventoryMovement|null $exitMovement
 */
#[Fillable(['store_id', 'product_id', 'reception_line_id', 'cost', 'inserted_at', 'delivered_at', 'exit_movement_id'])]
class InventoryUnit extends Model
{
    /** @use HasFactory<InventoryUnitFactory> */
    use HasFactory;

    protected $casts = [
        'cost' => MoneyCast::class,
        'inserted_at' => 'date',
        'delivered_at' => 'date',
    ];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<InventoryMovement, $this> */
    public function exitMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'exit_movement_id');
    }

    /** @param Builder<InventoryUnit> $query */
    public function scopeInHand(Builder $query): void
    {
        $query->whereNull('delivered_at');
    }

    /** @param Builder<InventoryUnit> $query */
    public function scopeFifo(Builder $query): void
    {
        $query->orderBy('inserted_at')->orderBy('id');
    }
}
