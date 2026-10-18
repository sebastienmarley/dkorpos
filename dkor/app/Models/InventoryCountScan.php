<?php

namespace App\Models;

use Database\Factories\InventoryCountScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lecture d'une feuille de compte : un code lu (vide pour une correction de quantité), la quantité ajoutée (négative
 * pour une correction à la baisse), et le produit, vide tant qu'un code inconnu n'est pas associé.
 *
 * @property int $id
 * @property int $inventory_count_sheet_id
 * @property string|null $code
 * @property int|null $product_id
 * @property int $quantity
 * @property int|null $user_id
 * @property Carbon|null $undone_at
 * @property Carbon $created_at
 * @property-read InventoryCountSheet $sheet
 * @property-read Product|null $product
 */
#[Fillable(['inventory_count_sheet_id', 'code', 'product_id', 'quantity', 'user_id', 'undone_at'])]
class InventoryCountScan extends Model
{
    /** @use HasFactory<InventoryCountScanFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'quantity' => 'integer',
        'undone_at' => 'datetime',
    ];

    public function isUnknown(): bool
    {
        return $this->product_id === null && $this->undone_at === null;
    }

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
