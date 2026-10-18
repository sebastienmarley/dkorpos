<?php

namespace App\Models;

use App\Enums\LabelReason;
use Database\Factories\LabelRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Étiquette de prix en attente d'impression (ou déjà imprimée, pour l'historique).
 *
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property LabelReason $reason
 * @property int|null $requested_by
 * @property Carbon|null $printed_at
 * @property int|null $printed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read User|null $requester
 */
#[Fillable(['product_id', 'quantity', 'reason', 'requested_by', 'printed_at', 'printed_by'])]
class LabelRequest extends Model
{
    /** @use HasFactory<LabelRequestFactory> */
    use HasFactory;

    protected $casts = [
        'reason' => LabelReason::class,
        'printed_at' => 'datetime',
    ];

    /**
     * Ajoute des étiquettes à la file; une demande en attente pour le même produit et la même raison est cumulée.
     */
    public static function enqueue(Product $product, int $quantity, LabelReason $reason = LabelReason::Manual, ?User $requester = null): self
    {
        $request = self::query()->pending()->firstOrNew(['product_id' => $product->id, 'reason' => $reason]);

        $request->quantity = ($request->quantity ?? 0) + $quantity;
        $request->requested_by ??= $requester?->id;
        $request->save();

        return $request;
    }

    /**
     * Marque comme imprimées les demandes encore en attente parmi celles données.
     *
     * @param  array<int, int>  $ids
     */
    public static function markPrinted(array $ids, ?User $printer = null): int
    {
        return self::query()->pending()->whereKey($ids)->update([
            'printed_at' => now(),
            'printed_by' => $printer?->id,
        ]);
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('printed_at');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
