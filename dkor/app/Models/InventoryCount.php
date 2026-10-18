<?php

namespace App\Models;

use App\Enums\InventoryCountPass;
use App\Enums\InventoryCountResultSource;
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
use Illuminate\Support\Facades\Gate;
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
 * Comptes doubles (feuilles de compte, InventoryCountSheet) : chaque type entier, et chaque zone du stock, est compté par
 * deux compteurs (A et B). Les produits en désaccord sont recomptés par un troisième; la portée réconciliée alimente
 * les lignes de la prise, qui ne se saisissent plus à la main. Sans feuilles, les lignes se saisissent comme avant.
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
 * @property-read Collection<int, InventoryCountSheet> $sheets
 * @property-read Collection<int, InventoryCountResult> $results
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

            if ($this->hasSheetsFor($line->status)) {
                $retained = $this->retainedQuantity($line->product_id, $line->status);

                if ($counted !== null && $counted !== $retained) {
                    throw new DomainException(__('Cette ligne est alimentée par les feuilles de compte : elle ne se saisit pas à la main.'));
                }

                $counted = $retained;
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
     * Prépare les feuilles de compte A et B : une par type entier (défectueux, démonstrateur, stock client) et une par
     * zone active du stock. Les feuilles déjà préparées sont conservées.
     */
    public function prepareSheets(): void
    {
        DB::transaction(function (): void {
            $this->refreshForUpdate();
            $this->guardInProgress();

            $scopes = collect([InventoryStatus::DefectiveStock, InventoryStatus::InDemo, InventoryStatus::ReservedCustomer])
                ->map(fn (InventoryStatus $status): array => [$status, null])
                ->merge(InventoryZone::query()->active()->ordered()->pluck('id')->map(fn (int $zoneId): array => [InventoryStatus::InStock, $zoneId]));

            foreach ($scopes as [$status, $zoneId]) {
                foreach ([InventoryCountPass::A, InventoryCountPass::B] as $pass) {
                    $this->sheets()->firstOrCreate(['status' => $status, 'inventory_zone_id' => $zoneId, 'pass' => $pass]);
                }
            }
        });
    }

    /**
     * Attribue une feuille à un compteur. Une même personne ne fait pas deux passes d'une même portée.
     */
    public function assignSheet(InventoryCountSheet $sheet, User $counter): void
    {
        DB::transaction(function () use ($sheet, $counter): void {
            $this->refreshForUpdate();
            $this->guardInProgress();
            $sheet->refreshForUpdate();

            if ($sheet->inventory_count_id !== $this->id) {
                throw new DomainException(__('Cette feuille n\'appartient pas à la prise d\'inventaire.'));
            }

            if ($sheet->isDone()) {
                throw new DomainException(__('Cette feuille est terminée : elle ne se réattribue plus.'));
            }

            if (! Gate::forUser($counter)->allows('inventory.count')) {
                throw new DomainException(__(':name ne peut pas compter l\'inventaire.', ['name' => $counter->fullName()]));
            }

            $otherPass = $this->sheetsFor($sheet->status, $sheet->inventory_zone_id)
                ->where('id', '!=', $sheet->id)
                ->where('assigned_to', $counter->id)
                ->exists();

            if ($otherPass) {
                throw new DomainException(__(':name fait déjà un autre compte de « :scope » : les comptes d\'une même portée sont faits par des personnes différentes.', [
                    'name' => $counter->fullName(),
                    'scope' => $sheet->areaLabel(),
                ]));
            }

            $sheet->update(['assigned_to' => $counter->id]);
        });
    }

    /**
     * Compare les comptes d'une portée, produit par produit : A, B, recompte (s'il existe) et concordance.
     *
     * @return list<array{product_id: int, a: int, b: int, recount: int|null, agreed: bool}>|null Vide tant que A et B ne sont pas terminés.
     */
    public function compareScope(InventoryStatus $status, ?int $zoneId): ?array
    {
        $sheets = $this->sheetsFor($status, $zoneId)->get()->keyBy(fn (InventoryCountSheet $sheet): string => $sheet->pass->value);
        $a = $sheets->get(InventoryCountPass::A->value);
        $b = $sheets->get(InventoryCountPass::B->value);

        if ($a === null || $b === null || ! $a->isDone() || ! $b->isDone()) {
            return null;
        }

        $recount = $sheets->get(InventoryCountPass::Recount->value);
        $quantitiesA = $a->quantities();
        $quantitiesB = $b->quantities();
        $quantitiesRecount = $recount?->isDone() ? $recount->quantities() : null;

        $productIds = array_values(array_unique([...array_keys($quantitiesA), ...array_keys($quantitiesB)]));
        sort($productIds);

        return array_map(fn (int $productId): array => [
            'product_id' => $productId,
            'a' => $quantitiesA[$productId] ?? 0,
            'b' => $quantitiesB[$productId] ?? 0,
            'recount' => $quantitiesRecount === null ? null : ($quantitiesRecount[$productId] ?? 0),
            'agreed' => ($quantitiesA[$productId] ?? 0) === ($quantitiesB[$productId] ?? 0),
        ], $productIds);
    }

    /**
     * Crée le recompte des produits en désaccord d'une portée, attribué à un troisième compteur (ni A ni B).
     */
    public function createRecount(InventoryStatus $status, ?int $zoneId, User $counter): InventoryCountSheet
    {
        return DB::transaction(function () use ($status, $zoneId, $counter): InventoryCountSheet {
            $this->refreshForUpdate();
            $this->guardInProgress();

            $comparison = $this->compareScope($status, $zoneId);

            if ($comparison === null) {
                throw new DomainException(__('Les comptes A et B doivent être terminés avant le recompte.'));
            }

            $disagreements = array_filter($comparison, fn (array $row): bool => ! $row['agreed']);

            if ($disagreements === []) {
                throw new DomainException(__('Les comptes A et B concordent : rien à recompter.'));
            }

            if ($this->sheetsFor($status, $zoneId)->where('pass', InventoryCountPass::Recount)->exists()) {
                throw new DomainException(__('Le recompte de cette portée existe déjà.'));
            }

            if ($this->sheetsFor($status, $zoneId)->where('assigned_to', $counter->id)->exists()) {
                throw new DomainException(__('Le recompte se fait par un troisième compteur, qui n\'a fait ni le compte A ni le compte B.'));
            }

            if (! Gate::forUser($counter)->allows('inventory.count')) {
                throw new DomainException(__(':name ne peut pas compter l\'inventaire.', ['name' => $counter->fullName()]));
            }

            $sheet = $this->sheets()->create([
                'status' => $status,
                'inventory_zone_id' => $zoneId,
                'pass' => InventoryCountPass::Recount,
                'assigned_to' => $counter->id,
            ]);

            foreach ($disagreements as $row) {
                $sheet->lines()->create(['product_id' => $row['product_id'], 'quantity' => 0]);
            }

            return $sheet;
        });
    }

    /**
     * Réconcilie une portée : chaque produit dont A et B concordent est retenu; un produit en désaccord retient la
     * quantité du recompte si elle concorde avec A ou B, sinon seulement si le superviseur la retient
     * ($acceptRecount). Les lignes de la prise sont ensuite alimentées.
     *
     * @throws DomainException si les comptes ne sont pas terminés, si le recompte manque ou reste à trancher.
     */
    public function reconcileScope(InventoryStatus $status, ?int $zoneId, User $supervisor, bool $acceptRecount = false): void
    {
        DB::transaction(function () use ($status, $zoneId, $supervisor, $acceptRecount): void {
            $this->refreshForUpdate();
            $this->guardInProgress();

            if ($this->isScopeReconciled($status, $zoneId)) {
                throw new DomainException(__('Cette portée est déjà réconciliée.'));
            }

            $comparison = $this->compareScope($status, $zoneId);

            if ($comparison === null) {
                throw new DomainException(__('Les comptes A et B doivent être terminés avant la réconciliation.'));
            }

            $results = [];
            $unresolved = 0;

            foreach ($comparison as $row) {
                if ($row['agreed']) {
                    $results[] = [$row['product_id'], $row['a'], InventoryCountResultSource::Agreed];
                } elseif ($row['recount'] === null) {
                    $unresolved++;
                } elseif (in_array($row['recount'], [$row['a'], $row['b']], true)) {
                    $results[] = [$row['product_id'], $row['recount'], InventoryCountResultSource::Recount];
                } elseif ($acceptRecount) {
                    $results[] = [$row['product_id'], $row['recount'], InventoryCountResultSource::Supervisor];
                } else {
                    $unresolved++;
                }
            }

            $recount = $this->sheetsFor($status, $zoneId)->where('pass', InventoryCountPass::Recount)->first();

            if ($unresolved > 0) {
                throw new DomainException(match (true) {
                    $recount === null => trans_choice('Créez le recompte du produit en désaccord.|Créez le recompte des :count produits en désaccord.', $unresolved),
                    ! $recount->isDone() => __('Le recompte doit être terminé avant la réconciliation.'),
                    default => trans_choice('Le recompte ne concorde ni avec A ni avec B pour :count produit : retenez le recompte pour réconcilier.|Le recompte ne concorde ni avec A ni avec B pour :count produits : retenez le recompte pour réconcilier.', $unresolved),
                });
            }

            foreach ($results as [$productId, $quantity, $source]) {
                $this->results()->create([
                    'status' => $status,
                    'inventory_zone_id' => $zoneId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'source' => $source,
                    'reconciled_by' => $supervisor->id,
                ]);
            }

            $this->sheetsFor($status, $zoneId)->update(['reconciled_at' => now()]);

            $this->applyResults($status, $supervisor);
        });
    }

    /**
     * Une fois toutes les portées d'un état réconciliées, la quantité comptée de chaque ligne de cet état devient la
     * somme des quantités retenues (zéro pour un produit trouvé nulle part).
     */
    public function applyResults(InventoryStatus $status, User $supervisor): void
    {
        if ($this->sheets()->where('status', $status)->whereNull('reconciled_at')->exists()) {
            return;
        }

        $retained = $this->results()->where('status', $status)->get()->groupBy('product_id')->map->sum('quantity');

        foreach ($retained->keys() as $productId) {
            $this->lines()->firstOrCreate(['product_id' => $productId, 'status' => $status]);
        }

        $this->lines()->where('status', $status)->where('is_kept', false)->get()->each(
            fn (InventoryCountLine $line) => $line->update([
                'counted_quantity' => (int) ($retained[$line->product_id] ?? 0),
                'counted_by' => $supervisor->id,
                'counted_at' => now(),
            ]),
        );
    }

    public function hasSheetsFor(InventoryStatus $status): bool
    {
        return $this->sheets()->where('status', $status)->exists();
    }

    public function isScopeReconciled(InventoryStatus $status, ?int $zoneId): bool
    {
        return $this->sheetsFor($status, $zoneId)->whereNotNull('reconciled_at')->exists();
    }

    /**
     * Portées qui ont des feuilles mais ne sont pas réconciliées.
     *
     * @return list<string>
     */
    public function unreconciledScopeLabels(): array
    {
        return array_values($this->sheets()->with('zone')->whereNull('reconciled_at')->where('pass', InventoryCountPass::A)->get()
            ->map(fn (InventoryCountSheet $sheet): string => $sheet->areaLabel())
            ->all());
    }

    /**
     * Quantité retenue d'un produit dans un état (somme des zones), ou vide tant que l'état n'est pas réconcilié.
     */
    public function retainedQuantity(int $productId, InventoryStatus $status): ?int
    {
        if ($this->sheets()->where('status', $status)->whereNull('reconciled_at')->exists()) {
            return null;
        }

        return (int) $this->results()->where('status', $status)->where('product_id', $productId)->sum('quantity');
    }

    /** @return HasMany<InventoryCountSheet, $this> */
    public function sheetsFor(InventoryStatus $status, ?int $zoneId): HasMany
    {
        return $this->sheets()->where('status', $status)->where('inventory_zone_id', $zoneId);
    }

    /** @return HasMany<InventoryCountSheet, $this> */
    public function sheets(): HasMany
    {
        return $this->hasMany(InventoryCountSheet::class);
    }

    /** @return HasMany<InventoryCountResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(InventoryCountResult::class);
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
