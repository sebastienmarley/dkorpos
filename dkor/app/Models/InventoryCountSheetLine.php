<?php

namespace App\Models;

use Database\Factories\InventoryCountSheetLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_count_sheet_id
 * @property int $product_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCountSheet $sheet
 * @property-read Product $product
 */
#[Fillable(['inventory_count_sheet_id', 'product_id', 'quantity'])]
class InventoryCountSheetLine extends Model
{
    /** @use HasFactory<InventoryCountSheetLineFactory> */
    use HasFactory;

    protected $attributes = [
        'quantity' => 0,
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /** @return BelongsTo<InventoryCountSheet, $this> */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSheet::class, 'inventory_count_sheet_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
