<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Ajustement d'inventaire (issue #52, ADR 0011) : une entrée ou une sortie sans document d'achat ni de vente, faite
 * par App\Actions\AdjustInventory. Son mouvement en donne la valeur; son écriture la passe au grand livre. Il ne se
 * modifie ni ne se supprime : une erreur se corrige par l'ajustement inverse.
 *
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property int $product_id
 * @property InventoryAdjustmentReason $reason
 * @property InventoryStatus $status
 * @property bool $is_entry
 * @property int $quantity
 * @property string $note
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Product $product
 * @property-read User|null $user
 * @property-read InventoryMovement|null $movement
 * @property-read LedgerEntry|null $ledgerEntry
 */
#[Fillable(['store_id', 'product_id', 'reason', 'status', 'is_entry', 'quantity', 'note', 'user_id'])]
class InventoryAdjustment extends Model
{
    use BelongsToStore;

    /** @use HasFactory<InventoryAdjustmentFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'reason' => InventoryAdjustmentReason::class,
        'status' => InventoryStatus::class,
        'is_entry' => 'boolean',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Un ajustement d\'inventaire ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Un ajustement d\'inventaire ne se supprime pas.'));
    }

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphOne<InventoryMovement, $this> */
    public function movement(): MorphOne
    {
        return $this->morphOne(InventoryMovement::class, 'reference');
    }

    /** @return MorphOne<LedgerEntry, $this> */
    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(LedgerEntry::class, 'reference');
    }
}
