<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ProductClearanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Liquidation d'un produit dans un magasin (ADR 0018) : le stock en main de ce magasin se vend au prix de
 * liquidation jusqu'à ce qu'on y mette fin. Une seule liquidation active par produit et par magasin ; elle ne se
 * supprime pas.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id Magasin du stock.
 * @property Money $price
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Store $store
 */
#[Fillable(['product_id', 'store_id', 'price', 'ended_at'])]
class ProductClearance extends Model
{
    /** @use HasFactory<ProductClearanceFactory> */
    use HasFactory;

    protected $casts = [
        'price' => MoneyCast::class,
        'ended_at' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @param  Builder<ProductClearance>  $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('ended_at');
    }
}
