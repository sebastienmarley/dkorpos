<?php

namespace App\Models;

use App\Enums\InventoryCountStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use Carbon\CarbonInterface;
use Database\Factories\InventoryCountFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Prise d'inventaire annuelle (issue #88, ADR 0011). Chaque état physique se compte à part (types de compte).
 *
 * - Au démarrage, les perdus non retrouvés reviennent « En stock » : « Perdu » est vide pendant toute la prise.
 * - Démo, défectueux et stock client se comptent pendant les heures d'ouverture, sans gel : leur attendu est la
 *   quantité au moment de la finalisation, et l'équipe des ventes note les variances sur les lignes.
 * - Le stock se compte après la fermeture : « Geler le stock » bloque tout mouvement qui touche « En stock » ou
 *   « Perdu » jusqu'à la finalisation ou l'abandon (InventoryMovement::record).
 *
 * La finalisation (App\Actions\AnnualInventoryFinalUpdate) fige l'attendu de chaque ligne et crée un ajustement par
 * écart, avec son écriture à la date de comptage. Une prise finalisée ou abandonnée ne change plus.
 *
 * @property int $id
 * @property Carbon $counted_on
 * @property InventoryCountStatus $status
 * @property Carbon|null $stock_frozen_at
 * @property int|null $started_by
 * @property Carbon|null $finalized_at
 * @property int|null $finalized_by
 * @property Carbon|null $abandoned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InventoryCountLine> $lines
 * @property-read User|null $starter
 * @property-read User|null $finalizer
 */
#[Fillable(['counted_on', 'status', 'stock_frozen_at', 'started_by', 'finalized_at', 'finalized_by', 'abandoned_at'])]
class InventoryCount extends Model
{
    /** @use HasFactory<InventoryCountFactory> */
    use HasFactory;

    protected $casts = [
        'counted_on' => 'date',
        'status' => InventoryCountStatus::class,
        'stock_frozen_at' => 'datetime',
        'finalized_at' => 'datetime',
        'abandoned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (InventoryCount $count): void {
            if ($count->getOriginal('status') !== InventoryCountStatus::InProgress) {
                throw new LogicException('Une prise d\'inventaire finalisée ou abandonnée ne se modifie pas.');
            }
        });

        static::deleting(fn () => throw new LogicException('Une prise d\'inventaire ne se supprime pas.'));
    }

    /**
     * Types de compte : les états physiques comptés, dans l'ordre de la page.
     *
     * @return array<string, string> Valeur de l'état => libellé du type de compte.
     */
    public static function countTypes(): array
    {
        return [
            InventoryStatus::InStock->value => __('Stock'),
            InventoryStatus::ReservedCustomer->value => __('Stock client'),
            InventoryStatus::DefectiveStock->value => __('Défectueux'),
            InventoryStatus::InDemo->value => __('Démonstrateur'),
        ];
    }

    /** @return list<InventoryStatus> */
    public static function countedStatuses(): array
    {
        return array_map(fn (string $status): InventoryStatus => InventoryStatus::from($status), array_keys(self::countTypes()));
    }

    public static function inProgress(): ?self
    {
        return static::query()->where('status', InventoryCountStatus::InProgress)->latest('id')->first();
    }

    /** Une prise en cours a gelé le stock : aucun mouvement ne touche « En stock » ni « Perdu ». */
    public static function stockIsFrozen(): bool
    {
        return static::query()->where('status', InventoryCountStatus::InProgress)->whereNotNull('stock_frozen_at')->exists();
    }

    /**
     * Démarre une prise : les perdus reviennent « En stock » (un mouvement par produit) et chaque produit qui a du
     * stock dans un type de compte reçoit sa ligne.
     *
     * @throws DomainException si une prise est déjà en cours.
     */
    public static function start(CarbonInterface $countedOn, User $startedBy): self
    {
        return DB::transaction(function () use ($countedOn, $startedBy): self {
            if (static::query()->where('status', InventoryCountStatus::InProgress)->lockForUpdate()->exists()) {
                throw new DomainException(__('Une prise d\'inventaire est déjà en cours : finalisez-la ou abandonnez-la.'));
            }

            $count = static::create([
                'counted_on' => $countedOn,
                'status' => InventoryCountStatus::InProgress,
                'started_by' => $startedBy->id,
            ]);

            $count->returnLostToStock($startedBy);
            $count->syncLines(self::countedStatuses());

            return $count;
        });
    }

    /**
     * Gèle le stock pour le compter après la fermeture. Les perdus déclarés depuis le démarrage reviennent d'abord
     * « En stock », et les lignes de stock manquantes sont ajoutées.
     */
    public function freezeStock(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->refreshForUpdate();
            $this->guardInProgress();

            if ($this->stock_frozen_at !== null) {
                throw new DomainException(__('Le stock est déjà gelé.'));
            }

            $this->returnLostToStock($user);
            $this->update(['stock_frozen_at' => now()]);
            $this->syncLines([InventoryStatus::InStock]);
        });
    }

    /**
     * Ajoute un produit trouvé dans un type de compte où il n'était pas attendu (ou retrouve sa ligne).
     */
    public function addLine(Product $product, InventoryStatus $status): InventoryCountLine
    {
        return DB::transaction(function () use ($product, $status): InventoryCountLine {
            $this->refreshForUpdate();
            $this->guardInProgress();
            $this->guardCounted($status);

            return $this->lines()->firstOrCreate(['product_id' => $product->id, 'status' => $status]);
        });
    }

    /**
     * Saisit le compte d'une ligne, ou la marque « non comptée, conservée » (son attendu est gardé tel quel). Le stock
     * se compte une fois gelé.
     *
     * @throws DomainException si la prise n'est plus en cours, si le stock n'est pas gelé ou si la quantité est invalide.
     */
    public function recordCount(InventoryCountLine $line, ?int $counted, bool $isKept, ?string $note, User $countedBy): void
    {
        DB::transaction(function () use ($line, $counted, $isKept, $note, $countedBy): void {
            $this->refreshForUpdate();
            $this->guardInProgress();

            if ($line->inventory_count_id !== $this->id) {
                throw new DomainException(__('Cette ligne n\'appartient pas à la prise d\'inventaire.'));
            }

            if ($line->status === InventoryStatus::InStock && $this->stock_frozen_at === null && $counted !== null) {
                throw new DomainException(__('Gelez le stock (après la fermeture) avant de le compter.'));
            }

            if ($counted !== null && $counted < 0) {
                throw new DomainException(__('La quantité comptée ne peut pas être négative.'));
            }

            $line->update([
                'counted_quantity' => $isKept ? null : $counted,
                'is_kept' => $isKept,
                'note' => filled($note) ? trim($note) : null,
                'counted_by' => $countedBy->id,
                'counted_at' => now(),
            ]);
        });
    }

    /**
     * Abandonne la prise : aucun ajustement, le stock est dégelé. Les perdus remis en stock y restent.
     */
    public function abandon(): void
    {
        DB::transaction(function (): void {
            $this->refreshForUpdate();
            $this->guardInProgress();

            $this->update(['status' => InventoryCountStatus::Abandoned, 'abandoned_at' => now()]);
        });
    }

    /**
     * Ajoute une ligne pour chaque produit qui a une quantité dans l'un des états donnés et n'a pas encore de ligne.
     *
     * @param  list<InventoryStatus>  $statuses
     */
    public function syncLines(array $statuses): void
    {
        $existing = $this->lines()->get()->map(fn (InventoryCountLine $line): string => $line->product_id.'-'.$line->status->value)->flip();

        InventoryStock::query()
            ->where(fn ($query) => collect($statuses)->each(fn (InventoryStatus $status) => $query->orWhere($status->column(), '>', 0)))
            ->get()
            ->each(function (InventoryStock $stock) use ($statuses, $existing): void {
                foreach ($statuses as $status) {
                    if ($stock->quantityFor($status) > 0 && ! $existing->has($stock->product_id.'-'.$status->value)) {
                        $this->lines()->create(['product_id' => $stock->product_id, 'status' => $status]);
                    }
                }
            });
    }

    public function isInProgress(): bool
    {
        return $this->status === InventoryCountStatus::InProgress;
    }

    public function guardInProgress(): void
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Cette prise d\'inventaire n\'est plus en cours.'));
        }
    }

    /** @return HasMany<InventoryCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    private function returnLostToStock(User $user): void
    {
        InventoryStock::query()->where('quantity_lost', '>', 0)->get()->each(
            fn (InventoryStock $stock) => InventoryMovement::record(
                $stock->product_id,
                InventoryStatus::Lost,
                InventoryStatus::InStock,
                $stock->quantity_lost,
                InventoryMovementType::CountLostReturned,
                $this,
                userId: $user->id,
            ),
        );
    }

    private function guardCounted(InventoryStatus $status): void
    {
        if (! in_array($status, self::countedStatuses(), true)) {
            throw new DomainException(__('L\'état « :status » ne se compte pas à l\'inventaire.', ['status' => $status->label()]));
        }
    }
}
