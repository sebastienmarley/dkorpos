<?php

namespace App\Models;

use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_list_list_id
 * @property int|null $product_id
 * @property string $model
 * @property string $clean_model
 * @property float|null $cost
 * @property float|null $imap
 * @property string|null $upc
 * @property string|null $collection
 * @property string|null $description
 * @property float|null $length
 * @property float|null $width
 * @property float|null $height
 * @property float|null $weight
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceListList $priceListList
 * @property-read Product|null $product
 */
#[Fillable(['price_list_list_id', 'product_id', 'model', 'clean_model', 'cost', 'imap', 'upc', 'collection', 'description', 'length', 'width', 'height', 'weight'])]
class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'float',
            'imap' => 'float',
            'length' => 'float',
            'width' => 'float',
            'height' => 'float',
            'weight' => 'float',
        ];
    }

    /** @return BelongsTo<PriceListList, $this> */
    public function priceListList(): BelongsTo
    {
        return $this->belongsTo(PriceListList::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
