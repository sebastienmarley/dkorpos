<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Emplacement habituel d'un produit dans l'entrepôt d'un magasin (issue #96) : il est proposé par défaut quand on
 * range ce produit.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property int $inventory_location_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryLocation $location
 */
#[Fillable(['product_id', 'store_id', 'inventory_location_id'])]
class ProductStoreLocation extends Model
{
    /** @return BelongsTo<InventoryLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }
}
