<?php

namespace App\Models;

use App\Enums\InventoryCountResultSource;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryCountResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quantité retenue d'un produit dans une portée réconciliée (état compté, et zone pour le stock).
 *
 * @property int $id
 * @property int $inventory_count_id
 * @property InventoryStatus $status
 * @property int|null $inventory_zone_id
 * @property int $product_id
 * @property int $quantity
 * @property InventoryCountResultSource $source
 * @property int|null $reconciled_by
 * @property Carbon $created_at
 * @property-read InventoryZone|null $zone
 * @property-read Product $product
 */
#[Fillable(['inventory_count_id', 'status', 'inventory_zone_id', 'product_id', 'quantity', 'source', 'reconciled_by'])]
class InventoryCountResult extends Model
{
    /** @use HasFactory<InventoryCountResultFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'status' => InventoryStatus::class,
        'quantity' => 'integer',
        'source' => InventoryCountResultSource::class,
    ];

    /** @return BelongsTo<InventoryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(InventoryZone::class, 'inventory_zone_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
