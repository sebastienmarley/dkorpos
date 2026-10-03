<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryMovementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Journal des mouvements d'inventaire: chaque changement de quantité d'un état à un autre y est consigné.
 * Un état source vide signifie que la marchandise vient de l'extérieur (ex.: commande au fournisseur); un état
 * destination vide, qu'elle sort du suivi (ex.: commande annulée). Le journal ne se modifie ni ne se supprime.
 *
 * @property int $id
 * @property int $product_id
 * @property InventoryStatus|null $from_status
 * @property InventoryStatus|null $to_status
 * @property int $quantity
 * @property InventoryMovementType $type
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Product $product
 * @property-read User|null $user
 * @property-read Model|null $reference
 */
#[Fillable(['product_id', 'from_status', 'to_status', 'quantity', 'type', 'reference_type', 'reference_id', 'user_id', 'note'])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'from_status' => InventoryStatus::class,
        'to_status' => InventoryStatus::class,
        'type' => InventoryMovementType::class,
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'inventaire ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'inventaire ne se supprime pas.'));
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

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Déplace une quantité d'un état à un autre (ou depuis/vers l'extérieur avec un état vide), met à jour les
     * quantités de l'inventaire et consigne le mouvement dans la même transaction.
     */
    public static function record(
        Product|int $product,
        ?InventoryStatus $from,
        ?InventoryStatus $to,
        int $quantity,
        InventoryMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        int|string|null $userId = null,
    ): InventoryMovement {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité à déplacer doit être d\'au moins 1.'));
        }

        if ($from === $to) {
            throw new DomainException(__('L\'état de départ et d\'arrivée doivent être différents.'));
        }

        $productId = $product instanceof Product ? $product->id : $product;

        return DB::transaction(function () use ($productId, $from, $to, $quantity, $type, $reference, $note, $userId): InventoryMovement {
            $stock = InventoryStock::query()->lockForUpdate()->firstOrCreate(['product_id' => $productId]);

            if ($from !== null) {
                $available = (int) $stock->{$from->column()};

                if ($available < $quantity) {
                    throw new DomainException(__('Quantité insuffisante « :status » (:available disponible, :quantity demandé).', [
                        'status' => $from->label(),
                        'available' => $available,
                        'quantity' => $quantity,
                    ]));
                }

                $stock->{$from->column()} = $available - $quantity;
            }

            if ($to !== null) {
                $stock->{$to->column()} = (int) $stock->{$to->column()} + $quantity;
            }

            $stock->save();

            return static::create([
                'product_id' => $productId,
                'from_status' => $from,
                'to_status' => $to,
                'quantity' => $quantity,
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId ?? auth()->id(),
                'note' => filled($note) ? $note : null,
            ]);
        });
    }
}
