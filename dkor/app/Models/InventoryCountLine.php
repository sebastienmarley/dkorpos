<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Support\Money;
use Database\Factories\InventoryCountLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'une prise d'inventaire : un produit dans un type de compte (état). L'attendu est la quantité courante de
 * l'état tant que la prise est en cours, puis la quantité figée à la finalisation.
 *
 * @property int $id
 * @property int $inventory_count_id
 * @property int $product_id
 * @property InventoryStatus $status
 * @property int|null $counted_quantity
 * @property bool $is_kept Non comptée, conservée : l'attendu est gardé, sans ajustement.
 * @property int|null $expected_quantity
 * @property string|null $note
 * @property int|null $counted_by
 * @property Carbon|null $counted_at
 * @property int|null $inventory_adjustment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCount $inventoryCount
 * @property-read Product $product
 * @property-read User|null $counter
 * @property-read InventoryAdjustment|null $adjustment
 */
#[Fillable(['inventory_count_id', 'product_id', 'status', 'counted_quantity', 'is_kept', 'expected_quantity', 'note', 'counted_by', 'counted_at', 'inventory_adjustment_id'])]
class InventoryCountLine extends Model
{
    /** @use HasFactory<InventoryCountLineFactory> */
    use HasFactory;

    protected $attributes = [
        'is_kept' => false,
    ];

    protected $casts = [
        'status' => InventoryStatus::class,
        'counted_quantity' => 'integer',
        'is_kept' => 'boolean',
        'expected_quantity' => 'integer',
        'counted_at' => 'datetime',
    ];

    /**
     * Quantité attendue : figée à la finalisation, sinon la quantité courante de l'état dans le magasin compté.
     */
    public function expected(): int
    {
        return $this->expected_quantity ?? $this->product->stockAt((int) $this->inventoryCount->store_id)?->quantityFor($this->status) ?? 0;
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null || $this->is_kept;
    }

    /** Quantité comptée, ou « Conservée », pour le rapport. */
    public function countedLabel(): string
    {
        return $this->is_kept ? __('Conservée') : (string) $this->counted_quantity;
    }

    /** Écart compté − attendu; vide si la ligne n'est pas comptée ou est conservée. */
    public function difference(): ?int
    {
        return $this->counted_quantity === null ? null : $this->counted_quantity - $this->expected();
    }

    /** Valeur de l'écart : celle de l'ajustement une fois finalisée, sinon une estimation au coût du produit. */
    public function differenceValue(): ?Money
    {
        if ($this->adjustment?->movement?->value !== null) {
            $value = $this->adjustment->movement->value;

            return $this->adjustment->is_entry ? $value : $value->negate();
        }

        $difference = $this->difference();

        return $difference === null ? null : $this->product->cost->times($difference);
    }

    /** @return BelongsTo<InventoryCount, $this> */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return BelongsTo<InventoryAdjustment, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }
}
