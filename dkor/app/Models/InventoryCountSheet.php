<?php

namespace App\Models;

use App\Enums\InventoryCountPass;
use App\Enums\InventoryCountSheetState;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryCountSheetFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Feuille de compte d'une prise d'inventaire (ADR 0011) : une portée (un état compté en entier, ou une zone pour le
 * stock), une passe (A, B ou recompte) et le compteur attribué. Le compteur lit les codes-barres à la douchette :
 * chaque lecture ajoute à la quantité du produit sur sa feuille. Un recompte se limite aux produits en désaccord.
 *
 * @property int $id
 * @property int $inventory_count_id
 * @property InventoryStatus $status
 * @property int|null $inventory_zone_id
 * @property InventoryCountPass $pass
 * @property int|null $assigned_to
 * @property InventoryCountSheetState $state
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $reconciled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCount $inventoryCount
 * @property-read InventoryZone|null $zone
 * @property-read User|null $assignee
 * @property-read Collection<int, InventoryCountSheetLine> $lines
 * @property-read Collection<int, InventoryCountScan> $scans
 */
#[Fillable(['inventory_count_id', 'status', 'inventory_zone_id', 'pass', 'assigned_to', 'state', 'started_at', 'finished_at', 'reconciled_at'])]
class InventoryCountSheet extends Model
{
    /** @use HasFactory<InventoryCountSheetFactory> */
    use HasFactory;

    protected $attributes = [
        'state' => 'todo',
    ];

    protected $casts = [
        'status' => InventoryStatus::class,
        'pass' => InventoryCountPass::class,
        'state' => InventoryCountSheetState::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    /** Portée de la feuille : « Stock — Allée 1 » ou « Démonstrateur ». */
    public function areaLabel(): string
    {
        $type = InventoryCount::countTypes()[$this->status->value];

        return $this->zone ? $type.' — '.$this->zone->name : $type;
    }

    public function isDone(): bool
    {
        return $this->state === InventoryCountSheetState::Done;
    }

    /**
     * Commence la feuille. Une feuille du stock attend le gel du stock (compté après la fermeture).
     */
    public function start(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->lockForCounter($user);

            if ($this->state !== InventoryCountSheetState::Todo) {
                return;
            }

            if ($this->status === InventoryStatus::InStock && $this->inventoryCount->stock_frozen_at === null) {
                throw new DomainException(__('Le stock se compte une fois gelé, après la fermeture.'));
            }

            $this->update(['state' => InventoryCountSheetState::InProgress, 'started_at' => now()]);
        });
    }

    /**
     * Lit un code-barres : la quantité s'ajoute au produit du code. Un code inconnu est consigné sans produit, pour
     * être associé plus tard (associate()).
     *
     * @throws DomainException si la feuille n'est pas au compteur, n'est pas commencée, ou si le produit n'est pas à recompter.
     */
    public function scan(User $user, string $code, int $quantity = 1): InventoryCountScan
    {
        return DB::transaction(function () use ($user, $code, $quantity): InventoryCountScan {
            $this->lockForCounter($user, InventoryCountSheetState::InProgress);

            $code = trim($code);

            if ($code === '') {
                throw new DomainException(__('Aucun code lu.'));
            }

            if ($quantity < 1) {
                throw new DomainException(__('La quantité lue doit être d\'au moins 1.'));
            }

            $productId = ProductUpc::query()->where('upc', $code)->value('product_id');

            if ($productId !== null) {
                $this->addToLine((int) $productId, $quantity);
            }

            return $this->scans()->create([
                'code' => $code,
                'product_id' => $productId,
                'quantity' => $quantity,
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Associe un code inconnu à un produit (comme dans la commande client) et ajoute sa quantité. Réservé à qui peut
     * modifier les produits ou superviser la prise, tant que la portée n'est pas réconciliée.
     */
    public function associate(InventoryCountScan $scan, Product $product, User $user): void
    {
        DB::transaction(function () use ($scan, $product, $user): void {
            $this->refreshForUpdate();
            $this->inventoryCount->guardInProgress();

            if (! Gate::forUser($user)->any(['products.edit', 'inventory.supervise'])) {
                throw new DomainException(__('Seul un superviseur ou un usager qui modifie les produits peut associer un code.'));
            }

            if ($scan->inventory_count_sheet_id !== $this->id || ! $scan->isUnknown()) {
                throw new DomainException(__('Cette lecture n\'est pas un code inconnu de la feuille.'));
            }

            if ($this->reconciled_at !== null) {
                throw new DomainException(__('Cette portée est déjà réconciliée.'));
            }

            $upc = ProductUpc::query()->firstOrCreate(['upc' => $scan->code], ['product_id' => $product->id]);

            $this->addToLine($upc->product_id, $scan->quantity);
            $scan->update(['product_id' => $upc->product_id]);
        });
    }

    /**
     * Annule la dernière lecture (ou correction) du compteur sur cette feuille.
     */
    public function undoLastScan(User $user): ?InventoryCountScan
    {
        return DB::transaction(function () use ($user): ?InventoryCountScan {
            $this->lockForCounter($user, InventoryCountSheetState::InProgress);

            $scan = $this->scans()->whereNull('undone_at')->latest('id')->first();

            if ($scan === null) {
                return null;
            }

            if ($scan->product_id !== null) {
                $this->addToLine($scan->product_id, -$scan->quantity);
            }

            $scan->update(['undone_at' => now()]);

            return $scan;
        });
    }

    /**
     * Corrige la quantité d'un produit de la feuille; la correction est consignée comme une lecture sans code.
     */
    public function setQuantity(User $user, Product $product, int $quantity): void
    {
        DB::transaction(function () use ($user, $product, $quantity): void {
            $this->lockForCounter($user, InventoryCountSheetState::InProgress);

            if ($quantity < 0) {
                throw new DomainException(__('La quantité ne peut pas être négative.'));
            }

            $current = (int) $this->lines()->where('product_id', $product->id)->value('quantity');

            if ($quantity === $current) {
                return;
            }

            $this->addToLine($product->id, $quantity - $current);
            $this->scans()->create(['code' => null, 'product_id' => $product->id, 'quantity' => $quantity - $current, 'user_id' => $user->id]);
        });
    }

    /**
     * Termine la feuille : elle n'est plus modifiable par le compteur et peut être comparée.
     */
    public function finish(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->lockForCounter($user, InventoryCountSheetState::InProgress);

            $this->update(['state' => InventoryCountSheetState::Done, 'finished_at' => now()]);
        });
    }

    /**
     * Quantités comptées de la feuille, par produit.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return $this->lines()->pluck('quantity', 'product_id')->map(fn ($quantity): int => (int) $quantity)->all();
    }

    /** @return BelongsTo<InventoryCount, $this> */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
    }

    /** @return BelongsTo<InventoryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(InventoryZone::class, 'inventory_zone_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<InventoryCountSheetLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountSheetLine::class);
    }

    /** @return HasMany<InventoryCountScan, $this> */
    public function scans(): HasMany
    {
        return $this->hasMany(InventoryCountScan::class);
    }

    /**
     * Relit la feuille sous verrou et vérifie qu'elle est au compteur, dans une prise en cours (et dans l'état voulu).
     */
    private function lockForCounter(User $user, ?InventoryCountSheetState $state = null): void
    {
        $this->refreshForUpdate();
        $this->inventoryCount->guardInProgress();

        if ($this->assigned_to !== $user->id) {
            throw new DomainException(__('Cette feuille de compte n\'est pas attribuée à vous.'));
        }

        if ($state !== null && $this->state !== $state) {
            throw new DomainException($this->isDone()
                ? __('Cette feuille est terminée : elle ne se modifie plus.')
                : __('Commencez la feuille avant de compter.'));
        }
    }

    private function addToLine(int $productId, int $quantity): void
    {
        $line = $this->lines()->where('product_id', $productId)->first();

        if ($line === null && $this->pass === InventoryCountPass::Recount) {
            throw new DomainException(__('Ce produit n\'est pas à recompter sur cette feuille.'));
        }

        $line ??= $this->lines()->create(['product_id' => $productId]);

        $line->update(['quantity' => max(0, $line->quantity + $quantity)]);
    }
}
