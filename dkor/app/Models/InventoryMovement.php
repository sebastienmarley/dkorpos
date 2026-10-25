<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\BelongsToStore;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Support\Money;
use Database\Factories\InventoryMovementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * Un mouvement qui fait entrer de la marchandise dans les états valorisés, ou l'en fait sortir, crée, sort ou rouvre
 * les unités d'inventaire (couches de coût FIFO) et consigne sa valeur (ADR 0011).
 *
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property int $product_id
 * @property InventoryStatus|null $from_status
 * @property InventoryStatus|null $to_status
 * @property int $quantity
 * @property Money|null $value Valeur des unités créées, sorties ou rouvertes; vide si les unités ne changent pas.
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
#[Fillable(['store_id', 'product_id', 'from_status', 'to_status', 'quantity', 'value', 'type', 'reference_type', 'reference_id', 'user_id', 'note'])]
class InventoryMovement extends Model
{
    use BelongsToStore;

    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $casts = [
        'from_status' => InventoryStatus::class,
        'to_status' => InventoryStatus::class,
        'type' => InventoryMovementType::class,
        'quantity' => 'integer',
        'value' => MoneyCast::class,
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'inventaire ne se modifie pas.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'inventaire ne se supprime pas.'));
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

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Déplace une quantité d'un état à un autre (ou depuis/vers l'extérieur avec un état vide) dans le stock d'un
     * magasin (ADR 0012), met à jour ses quantités et ses unités, et consigne le mouvement dans la même transaction.
     *
     * Unités (voir unitChange) : une entrée dans les états valorisés crée des unités au coût donné (sinon au coût du
     * produit), ou rouvre d'abord celles qu'une sortie de la même référence avait sorties (retour d'une commande).
     * Une sortie sort les plus anciennes (FIFO); un renversement de réception (référence ReceptionLine) supprime celles
     * de la ligne reçue.
     */
    public static function record(
        Product|int $product,
        Store|int $store,
        ?InventoryStatus $from,
        ?InventoryStatus $to,
        int $quantity,
        InventoryMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        int|string|null $userId = null,
        ?Money $unitCost = null,
    ): InventoryMovement {
        if ($quantity < 1) {
            throw new DomainException(__('La quantité à déplacer doit être d\'au moins 1.'));
        }

        if ($from === $to) {
            throw new DomainException(__('L\'état de départ et d\'arrivée doivent être différents.'));
        }

        if ($type === InventoryMovementType::Transfer && ! ($from?->isManuallyMovable() && $to?->isManuallyMovable())) {
            throw new DomainException(__('Un transfert manuel se fait seulement entre états physiques (en stock, en démo, défectueux, perdu).'));
        }

        $productId = $product instanceof Product ? $product->id : $product;
        $storeId = $store instanceof Store ? $store->id : $store;

        return DB::transaction(function () use ($productId, $storeId, $from, $to, $quantity, $type, $reference, $note, $userId, $unitCost): InventoryMovement {
            $frozen = [InventoryStatus::InStock, InventoryStatus::Lost];

            if ((in_array($from, $frozen, true) || in_array($to, $frozen, true)) && InventoryCount::stockIsFrozen($storeId)) {
                throw new DomainException(__('Le stock est gelé pour la prise d\'inventaire en cours : finalisez-la ou abandonnez-la avant de le déplacer.'));
            }

            $stock = InventoryStock::query()->lockForUpdate()->firstOrCreate(['product_id' => $productId, 'store_id' => $storeId]);

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

            $change = self::unitChange($productId, $storeId, $from, $to, $quantity, $reference, $unitCost);

            $movement = static::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'from_status' => $from,
                'to_status' => $to,
                'quantity' => $quantity,
                'value' => $change['value'],
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId ?? auth()->id(),
                'note' => filled($note) ? $note : null,
            ]);

            ($change['apply'])($movement);

            return $movement;
        });
    }

    /**
     * Fait passer une quantité d'un magasin à un autre (réception d'un transfert, issue #103) : elle sort de l'état
     * donné du magasin qui expédie et entre dans les états d'arrivée du magasin qui reçoit. Les mêmes unités FIFO
     * changent de magasin, à leur coût (les plus anciennes d'abord); la sortie et les entrées sont consignées au
     * journal, appariées par leur référence et du même montant.
     *
     * @param  array<string, int>  $arrivals  quantité par état d'arrivée (valeur de InventoryStatus), dans l'ordre où les unités y vont
     * @return array{out: InventoryMovement, in: list<InventoryMovement>}
     *
     * @throws DomainException si la quantité est invalide ou insuffisante, ou si le stock d'un des magasins est gelé.
     */
    public static function moveBetweenStores(
        Product|int $product,
        Store|int $fromStore,
        InventoryStatus $from,
        Store|int $toStore,
        array $arrivals,
        InventoryMovementType $outType,
        InventoryMovementType $inType,
        Model $reference,
        int|string|null $userId = null,
    ): array {
        $productId = $product instanceof Product ? $product->id : $product;
        $fromStoreId = $fromStore instanceof Store ? $fromStore->id : $fromStore;
        $toStoreId = $toStore instanceof Store ? $toStore->id : $toStore;
        $arrivals = array_filter($arrivals, fn (int $quantity): bool => $quantity > 0);
        $quantity = array_sum($arrivals);

        if ($quantity < 1) {
            throw new DomainException(__('La quantité à déplacer doit être d\'au moins 1.'));
        }

        if ($fromStoreId === $toStoreId) {
            throw new DomainException(__('Le magasin qui expédie et celui qui reçoit doivent être différents.'));
        }

        return DB::transaction(function () use ($productId, $fromStoreId, $from, $toStoreId, $arrivals, $quantity, $outType, $inType, $reference, $userId): array {
            foreach ([$fromStoreId, $toStoreId] as $storeId) {
                if (InventoryCount::stockIsFrozen($storeId)) {
                    throw new DomainException(__('Le stock d\'un des magasins est gelé pour une prise d\'inventaire : le transfert attend sa finalisation.'));
                }
            }

            $origin = InventoryStock::query()->lockForUpdate()->firstOrCreate(['product_id' => $productId, 'store_id' => $fromStoreId]);
            $available = $origin->quantityFor($from);

            if ($available < $quantity) {
                throw new DomainException(__('Quantité insuffisante « :status » (:available disponible, :quantity demandé).', ['status' => $from->label(), 'available' => $available, 'quantity' => $quantity]));
            }

            $origin->{$from->column()} = $available - $quantity;
            $origin->save();

            $destination = InventoryStock::query()->lockForUpdate()->firstOrCreate(['product_id' => $productId, 'store_id' => $toStoreId]);

            foreach ($arrivals as $status => $arrived) {
                $column = InventoryStatus::from($status)->column();
                $destination->{$column} = (int) $destination->{$column} + $arrived;
            }

            $destination->save();

            $units = InventoryUnit::query()->where('product_id', $productId)->where('store_id', $fromStoreId)->inHand()->fifo()->limit($quantity)->get();
            $productCost = self::productCost($productId);

            // Stock entré avant le suivi des unités : les unités manquantes sont créées au coût du produit.
            for ($i = $units->count(); $i < $quantity; $i++) {
                $units->push(InventoryUnit::create(['store_id' => $fromStoreId, 'product_id' => $productId, 'cost' => $productCost, 'inserted_at' => today()]));
            }

            InventoryUnit::whereKey($units->modelKeys())->update(['store_id' => $toStoreId]);

            $userId ??= auth()->id();
            $out = static::create([
                'store_id' => $fromStoreId,
                'product_id' => $productId,
                'from_status' => $from,
                'to_status' => null,
                'quantity' => $quantity,
                'value' => self::unitsValue($units),
                'type' => $outType,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'user_id' => $userId,
            ]);

            $in = [];
            $offset = 0;

            foreach ($arrivals as $status => $arrived) {
                $in[] = static::create([
                    'store_id' => $toStoreId,
                    'product_id' => $productId,
                    'from_status' => null,
                    'to_status' => InventoryStatus::from($status),
                    'quantity' => $arrived,
                    'value' => self::unitsValue($units->slice($offset, $arrived)),
                    'type' => $inType,
                    'reference_type' => $reference->getMorphClass(),
                    'reference_id' => $reference->getKey(),
                    'user_id' => $userId,
                ]);
                $offset += $arrived;
            }

            return ['out' => $out, 'in' => $in];
        });
    }

    /**
     * Prévoit le changement d'unités d'un mouvement : sa valeur, et ce qu'il faut faire une fois le mouvement créé
     * (sortir des unités demande son id). Rien ne change entre deux états valorisés ou deux états non valorisés.
     *
     * @return array{value: Money|null, apply: callable(InventoryMovement): void}
     */
    private static function unitChange(int $productId, int $storeId, ?InventoryStatus $from, ?InventoryStatus $to, int $quantity, ?Model $reference, ?Money $unitCost): array
    {
        $fromValued = $from?->isValued() ?? false;
        $toValued = $to?->isValued() ?? false;

        if ($fromValued === $toValued) {
            return ['value' => null, 'apply' => function (): void {}];
        }

        $inHand = fn (): Builder => InventoryUnit::query()->where('product_id', $productId)->where('store_id', $storeId)->inHand();

        if ($toValued) {
            $reopened = $from === null && $reference !== null
                ? InventoryUnit::query()
                    ->where('product_id', $productId)
                    ->where('store_id', $storeId)
                    ->whereNotNull('delivered_at')
                    ->whereHas('exitMovement', fn (Builder $exit) => $exit->whereMorphedTo('reference', $reference))
                    ->orderByDesc('delivered_at')
                    ->orderByDesc('id')
                    ->limit($quantity)
                    ->get()
                : new Collection;
            $created = $quantity - $reopened->count();
            $cost = $unitCost ?? self::productCost($productId);

            return [
                'value' => self::unitsValue($reopened)->plus($cost->times($created)),
                'apply' => function () use ($reopened, $created, $productId, $storeId, $cost, $reference): void {
                    InventoryUnit::whereKey($reopened->modelKeys())->update(['delivered_at' => null, 'exit_movement_id' => null]);

                    for ($i = 0; $i < $created; $i++) {
                        InventoryUnit::create([
                            'store_id' => $storeId,
                            'product_id' => $productId,
                            'reception_line_id' => $reference instanceof ReceptionLine ? $reference->id : null,
                            'cost' => $cost,
                            'inserted_at' => today(),
                        ]);
                    }
                },
            ];
        }

        if ($reference instanceof ReceptionLine && $to !== null) {
            $units = $inHand()->where('reception_line_id', $reference->id)->orderByDesc('id')->limit($quantity)->get();

            if ($units->count() < $quantity) {
                throw new DomainException(__('Impossible de renverser : certaines unités reçues ont déjà été livrées ou ne sont pas retraçables.'));
            }

            return [
                'value' => self::unitsValue($units),
                'apply' => function () use ($units): void {
                    InventoryUnit::whereKey($units->modelKeys())->delete();
                },
            ];
        }

        $units = $inHand()->fifo()->limit($quantity)->get();

        return [
            // Stock entré avant le suivi des unités : la partie sans unité est valorisée au coût du produit.
            'value' => self::unitsValue($units)->plus(self::productCost($productId)->times($quantity - $units->count())),
            'apply' => function (InventoryMovement $movement) use ($units): void {
                InventoryUnit::whereKey($units->modelKeys())->update(['delivered_at' => today(), 'exit_movement_id' => $movement->id]);
            },
        ];
    }

    /** @param Collection<int, InventoryUnit> $units */
    private static function unitsValue(Collection $units): Money
    {
        return Money::sum($units->map(fn (InventoryUnit $unit): Money => $unit->cost));
    }

    private static function productCost(int $productId): Money
    {
        return Product::query()->findOrFail($productId)->cost;
    }
}
