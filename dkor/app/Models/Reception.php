<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\ReceptionStatus;
use App\Enums\SupplierType;
use App\Mail\ReceptionDiscrepancyReported;
use App\Support\Money;
use Database\Factories\ReceptionFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * @property int $id
 * @property int|null $store_id Magasin (ADR 0012).
 * @property string|null $number
 * @property int $supplier_id
 * @property ReceptionStatus $status
 * @property int|null $received_by
 * @property Carbon $received_at
 * @property Carbon|null $completed_at
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read User|null $receiver
 * @property-read Collection<int, ReceptionLine> $lines
 * @property-read Money $total
 */
#[Fillable(['store_id', 'number', 'supplier_id', 'status', 'received_by', 'received_at', 'completed_at', 'reference', 'notes'])]
class Reception extends Model
{
    use BelongsToStore;

    /** @use HasFactory<ReceptionFactory> */
    use HasFactory;

    protected $casts = [
        'status' => ReceptionStatus::class,
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Reception $reception): void {
            if ($reception->number === null) {
                $reception->number = 'RC-'.str_pad((string) $reception->id, 6, '0', STR_PAD_LEFT);
                $reception->saveQuietly();
            }
        });
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<ReceptionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceptionLine::class)->orderBy('id');
    }

    /** @return HasOne<SupplierInvoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(SupplierInvoice::class);
    }

    /**
     * Lignes de cette réception à facturer : reçues (après renversements) ou manquantes.
     *
     * @return HasMany<ReceptionLine, $this>
     */
    public function invoiceableLines(): HasMany
    {
        return $this->lines()->whereRaw('quantity + quantity_short > quantity_reversed');
    }

    public function getTotalAttribute(): Money
    {
        return Money::sum($this->lines->map(fn (ReceptionLine $line): Money => $line->total));
    }

    public function isInProgress(): bool
    {
        return $this->status === ReceptionStatus::InProgress;
    }

    /**
     * Commence une réception: elle contient les lignes de commande choisies et leur quantité, sans effet sur
     * l'inventaire tant qu'elle n'est pas terminée. Les quantités sont plafonnées à ce qui reste à recevoir; la
     * quantité endommagée (facultative) fait partie de la quantité reçue; la quantité manquante (facultative) est
     * annoncée par le fournisseur, mais non reçue. Avec une quantité attendue, la ligne reste à vérifier (on compte
     * ensuite ce qui est reçu); sans elle, la quantité saisie est attendue et déjà vérifiée.
     *
     * @param  array<int, array{quantity: int|string, expected?: int|string|null, damaged?: int|string|null, short?: int|string|null}>  $receipts  Indexé par id de ligne de commande.
     * @param  array{reference?: string|null, notes?: string|null, received_by?: int|string|null}  $details
     */
    public static function start(Supplier $supplier, array $receipts, array $details = []): Reception
    {
        $receipts = array_filter($receipts, fn (array $receipt) => (int) ($receipt['expected'] ?? 0) + (int) $receipt['quantity'] + (int) ($receipt['short'] ?? 0) > 0);

        if ($receipts === []) {
            throw new DomainException(__('Saisissez au moins une quantité à réceptionner.'));
        }

        return DB::transaction(function () use ($supplier, $receipts, $details): Reception {
            $lines = SupplierOrderLine::query()
                ->with('order')
                ->whereIn('id', array_keys($receipts))
                ->orderBy('id')
                ->get();

            if ($lines->count() !== count($receipts)) {
                throw new DomainException(__('Une ligne sélectionnée est introuvable.'));
            }

            foreach ($lines as $line) {
                $order = $line->order;

                if ($order->supplier_id !== $supplier->id || $order->type !== SupplierType::Product || ! $order->status->isOpen()) {
                    throw new DomainException(__('La commande :number ne peut pas être réceptionnée pour ce fournisseur.', ['number' => $order->number]));
                }
            }

            $storeIds = $lines->map(fn (SupplierOrderLine $line): ?int => $line->order->store_id)->unique()->values();

            if ($storeIds->count() > 1) {
                throw new DomainException(__('Une réception ne couvre que les commandes d\'un seul magasin.'));
            }

            $storeId = $storeIds->first();
            $receiver = isset($details['received_by']) ? User::query()->find($details['received_by']) : null;

            if ($receiver !== null && ! $receiver->canActForStore($storeId)) {
                throw new DomainException(__('Cette commande est destinée à un autre magasin : vous ne pouvez pas la recevoir.'));
            }

            $reception = static::create([
                'store_id' => $storeId,
                'supplier_id' => $supplier->id,
                'status' => ReceptionStatus::InProgress,
                'received_by' => $details['received_by'] ?? null,
                'received_at' => now(),
                'reference' => filled($details['reference'] ?? null) ? $details['reference'] : null,
                'notes' => filled($details['notes'] ?? null) ? $details['notes'] : null,
            ]);

            foreach ($lines as $line) {
                $quantity = min(max(0, (int) $receipts[$line->id]['quantity']), $line->quantity_outstanding);
                $short = min(max(0, (int) ($receipts[$line->id]['short'] ?? 0)), $line->quantity_outstanding - $quantity);
                $hasExpected = isset($receipts[$line->id]['expected']);
                $expected = $hasExpected ? min(max(0, (int) $receipts[$line->id]['expected']), $line->quantity_outstanding) : 0;

                if ($expected + $quantity + $short > 0) {
                    $reception->lines()->create([
                        'supplier_order_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'quantity' => $quantity,
                        'quantity_expected' => max($expected, $quantity + $short),
                        'quantity_damaged' => self::damagedQuantity($line, $quantity, $receipts[$line->id]['damaged'] ?? 0),
                        'quantity_short' => $short,
                        'checked_at' => $hasExpected ? null : now(),
                        'unit_cost' => $line->unit_cost,
                    ]);
                }
            }

            if ($reception->lines()->doesntExist()) {
                throw new DomainException(__('Saisissez au moins une quantité à réceptionner.'));
            }

            return $reception;
        });
    }

    /**
     * Quantité d'un article reçu en trop : la ligne de commande suit la quantité reçue et il n'y a pas de manquant.
     *
     * @throws DomainException si la quantité est invalide.
     */
    private function applyOvershipQuantity(ReceptionLine $line, int $quantity, int $damaged): void
    {
        $orderLine = $line->orderLine;

        if ($quantity < 1) {
            throw new DomainException(__('La quantité reçue en trop doit être d\'au moins 1.'));
        }

        if ($quantity !== $orderLine->quantity) {
            $orderLine->order->updateLine($orderLine, $quantity, Money::zero());
            $orderLine->refresh();
        }

        if ($quantity > $orderLine->quantity_outstanding) {
            throw new DomainException(__('La quantité reçue en trop doit être entre 1 et :max.', ['max' => $orderLine->quantity_outstanding]));
        }

        $line->update([
            'quantity' => $quantity,
            'quantity_expected' => $quantity,
            'quantity_damaged' => self::damagedQuantity($orderLine, $quantity, $damaged),
            'quantity_short' => 0,
            'checked_at' => now(),
        ]);
    }

    /**
     * Lignes de la réception regroupées par produit (ReceptionLine::groupKey()), chaque groupe trié pour servir
     * d'abord les articles commandés pour un client.
     *
     * @return SupportCollection<string, Collection<int, ReceptionLine>>
     */
    public function lineGroups(): SupportCollection
    {
        $this->loadMissing(['lines.orderLine.customerOrderLine', 'lines.orderLine.order']);

        return $this->lines
            ->sortBy(fn (ReceptionLine $line): array => [$line->isForCustomer() ? 0 : 1, $line->id])
            ->groupBy(fn (ReceptionLine $line): string => $line->groupKey());
    }

    /**
     * Enregistre ce qui est réellement reçu d'un produit de la réception en cours, toutes ses lignes ensemble, et le
     * marque vérifié. Les unités en bon état servent d'abord les clients; les unités endommagées et les manquants
     * vont d'abord aux lignes pour l'inventaire. Ce qui n'est ni reçu ni manquant reste en commande.
     *
     * @throws DomainException si une quantité est invalide.
     */
    public function receiveGroup(string $groupKey, int $received, int $damaged = 0, int $short = 0): void
    {
        DB::transaction(function () use ($groupKey, $received, $damaged, $short): void {
            $this->refreshForUpdate();
            $this->allocateGroup($this->groupOrFail($groupKey), $received, $damaged, $short, checked: true);
        });

        $this->unsetRelation('lines');
    }

    /**
     * Compte une unité balayée (UPC) dans le produit attendu correspondant. Le produit devient vérifié quand tout ce
     * qui est attendu est reçu ou déclaré manquant.
     *
     * @return Collection<int, ReceptionLine> les lignes du produit compté
     *
     * @throws DomainException si le code est inconnu, si le produit n'est pas attendu ou s'il est déjà entièrement reçu.
     */
    public function receiveScannedUpc(string $upc): Collection
    {
        $productIds = ProductUpc::query()->where('upc', trim($upc))->pluck('product_id');

        if ($productIds->isEmpty()) {
            throw new DomainException(__('Aucun produit ne porte le code « :upc ».', ['upc' => trim($upc)]));
        }

        return DB::transaction(function () use ($productIds, $upc): Collection {
            $this->refreshForUpdate()->unsetRelation('lines');

            if (! $this->isInProgress()) {
                throw new DomainException(__('Cette réception est terminée.'));
            }

            $groupKey = $productIds->map(fn (int $productId): string => 'product-'.$productId)
                ->first(fn (string $key): bool => $this->lineGroups()->has($key));

            if ($groupKey === null) {
                throw new DomainException(__('Le produit « :upc » n\'est pas attendu dans cette réception : ajoutez-le comme article reçu en trop.', ['upc' => trim($upc)]));
            }

            $lines = $this->lineGroups()->get($groupKey);
            $received = $lines->sum('quantity') + 1;
            $short = $lines->sum('quantity_short');

            if ($received + $short > $lines->sum('quantity_expected')) {
                throw new DomainException(__('Tout ce qui est attendu de :label est déjà compté : ajoutez le surplus comme article reçu en trop.', ['label' => $lines->first()->label]));
            }

            $checked = $received + $short === $lines->sum('quantity_expected') || $lines->every(fn (ReceptionLine $line): bool => $line->checked_at !== null);
            $this->allocateGroup($lines, $received, $lines->sum('quantity_damaged'), $short, $checked);

            return $lines->each->refresh();
        });
    }

    /**
     * @return Collection<int, ReceptionLine>
     *
     * @throws DomainException si le produit n'est pas dans la réception.
     */
    private function groupOrFail(string $groupKey): Collection
    {
        $this->unsetRelation('lines');
        $lines = $this->lineGroups()->get($groupKey);

        if ($lines === null || ! $this->isInProgress()) {
            throw new DomainException(__('Ce produit ne peut plus être modifié.'));
        }

        return $lines;
    }

    /**
     * Répartit les quantités d'un produit sur ses lignes, dans la limite de ce qui est attendu de chacune.
     *
     * @param  Collection<int, ReceptionLine>  $lines  triées, clients d'abord
     *
     * @throws DomainException si une quantité est invalide.
     */
    private function allocateGroup(Collection $lines, int $received, int $damaged, int $short, bool $checked): void
    {
        if ($lines->count() === 1 && $lines->first()->orderLine->is_overship) {
            $this->applyOvershipQuantity($lines->first(), $received, $damaged);

            return;
        }

        $expected = $lines->sum('quantity_expected');

        if ($received < 0 || $short < 0 || $received + $short > $expected) {
            throw new DomainException(__('La quantité reçue et la quantité manquante ne peuvent pas dépasser la quantité attendue (:max).', ['max' => $expected]));
        }

        if ($damaged < 0 || $damaged > $received) {
            throw new DomainException(__('La quantité endommagée doit être entre 0 et la quantité reçue (:max).', ['max' => $received]));
        }

        $damaged = $lines->first()->product_id === null ? 0 : $damaged;
        $remaining = $lines->mapWithKeys(fn (ReceptionLine $line): array => [$line->id => $line->quantity_expected])->all();
        $customersFirst = $lines->pluck('id')->all();
        $stockFirst = array_reverse($customersFirst);

        $good = self::distribute($remaining, $customersFirst, $received - $damaged);
        $damagedByLine = self::distribute($remaining, $stockFirst, $damaged);
        $shortByLine = self::distribute($remaining, $stockFirst, $short);

        foreach ($lines as $line) {
            $line->update([
                'quantity' => $good[$line->id] + $damagedByLine[$line->id],
                'quantity_damaged' => $damagedByLine[$line->id],
                'quantity_short' => $shortByLine[$line->id],
                'checked_at' => $checked ? ($line->checked_at ?? now()) : null,
            ]);
        }
    }

    /**
     * Prend une quantité dans ce qui reste à combler de chaque ligne, dans l'ordre donné.
     *
     * @param  array<int, int>  $remaining  reste par ligne, diminué de ce qui est pris
     * @param  array<int, int>  $order  ids des lignes
     * @return array<int, int> quantité prise par ligne
     */
    private static function distribute(array &$remaining, array $order, int $quantity): array
    {
        $taken = [];

        foreach ($order as $lineId) {
            $taken[$lineId] = min($quantity, $remaining[$lineId]);
            $remaining[$lineId] -= $taken[$lineId];
            $quantity -= $taken[$lineId];
        }

        return $taken;
    }

    /**
     * Ajoute à la réception en cours un article reçu en trop (absent de la commande, ou en plus de ce qui reste à
     * recevoir) : une ligne à coût nul est ajoutée à la commande du fournisseur, puis reçue. Il entre en inventaire
     * à la fin de la réception; la comptabilité met son coût réel sur la facture si le fournisseur le facture.
     *
     * @throws DomainException si la réception est terminée, si le produit n'est pas de ce fournisseur ou si aucune
     *                         commande de la réception ne peut recevoir l'article.
     */
    public function addOvership(Product $product, int $quantity, ?SupplierOrder $order = null): ReceptionLine
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Cette réception est terminée.'));
        }

        if ($product->supplier_id !== $this->supplier_id || $product->is_custom) {
            throw new DomainException(__('Ce produit ne peut pas être reçu chez ce fournisseur.'));
        }

        if ($quantity < 1) {
            throw new DomainException(__('La quantité reçue en trop doit être d\'au moins 1.'));
        }

        $order ??= $this->lines()->with('orderLine.order')->get()->map(fn (ReceptionLine $line) => $line->orderLine->order)->first();

        if ($order === null || $order->supplier_id !== $this->supplier_id || ! $order->isProductOrder() || ! $order->status->isOpen()) {
            throw new DomainException(__('Ajoutez d\'abord une commande ouverte de ce fournisseur à la réception.'));
        }

        return DB::transaction(function () use ($product, $quantity, $order): ReceptionLine {
            $this->refreshForUpdate();

            if (! $this->isInProgress()) {
                throw new DomainException(__('Cette réception est terminée.'));
            }

            $orderLine = $order->addLine([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => Money::zero(),
                'is_overship' => true,
            ]);

            $line = $this->lines()->create([
                'supplier_order_line_id' => $orderLine->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'quantity_expected' => $quantity,
                'checked_at' => now(),
                'unit_cost' => Money::zero(),
            ]);

            $this->unsetRelation('lines');

            return $line;
        });
    }

    /**
     * Quantité endommagée valide : entre 0 et la quantité reçue, et seulement pour un produit (une ligne libre
     * n'entre pas en inventaire).
     *
     * @throws DomainException si elle dépasse la quantité reçue.
     */
    private static function damagedQuantity(SupplierOrderLine $orderLine, int $quantity, int|string|null $damaged): int
    {
        $damaged = (int) $damaged;

        if ($damaged < 0 || $damaged > $quantity) {
            throw new DomainException(__('La quantité endommagée doit être entre 0 et la quantité reçue (:max).', ['max' => $quantity]));
        }

        return $orderLine->product_id === null ? 0 : $damaged;
    }

    /**
     * Retire une ligne d'une réception en cours.
     */
    public function removeLine(ReceptionLine $line): void
    {
        DB::transaction(function () use ($line): void {
            $this->refreshForUpdate();
            $this->guardInProgress($line);

            $orderLine = $line->orderLine;
            $line->delete();

            if ($orderLine->is_overship) {
                $orderLine->order->removeOvershipLine($orderLine);
            }
        });

        $this->unsetRelation('lines');
    }

    /**
     * Met à jour le bordereau et les notes d'une réception en cours.
     */
    public function updateDetails(?string $reference, ?string $notes): void
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Cette réception est terminée.'));
        }

        $this->update([
            'reference' => filled($reference) ? $reference : null,
            'notes' => filled($notes) ? $notes : null,
        ]);
    }

    /**
     * Termine la réception: les quantités entrent en inventaire (unités FIFO au coût de la commande, stock en main,
     * « en commande »), les lignes de commande et leur statut sont mis à jour et le journal d'inventaire consigné.
     * S'il y a des articles manquants ou reçus en trop, le fournisseur en est avisé par courriel (demande de crédit).
     *
     * @return bool Faux seulement si un courriel d'écart devait partir mais n'a pas pu être envoyé.
     */
    public function complete(): bool
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Cette réception est déjà terminée.'));
        }

        DB::transaction(function (): void {
            $this->refreshForUpdate();

            if (! $this->isInProgress()) {
                throw new DomainException(__('Cette réception est déjà terminée.'));
            }

            if ($this->lines()->whereNull('checked_at')->exists()) {
                throw new DomainException(__('Vérifiez chaque produit avant de terminer la réception.'));
            }

            $lines = $this->lines()->with('orderLine.order')->get();

            foreach ($lines->groupBy(fn (ReceptionLine $line) => $line->orderLine->supplier_order_id) as $orderLines) {
                $order = $orderLines->first()->orderLine->order;

                if ($order->supplier_id !== $this->supplier_id || ! $order->status->isOpen()) {
                    throw new DomainException(__('La commande :number ne peut plus être réceptionnée.', ['number' => $order->number]));
                }

                $order->applyReceiptLines($this, $orderLines);
            }

            if ($this->lines()->doesntExist()) {
                throw new DomainException(__('Il ne reste rien à réceptionner : les lignes sont annulées ou déjà reçues.'));
            }

            $this->update(['status' => ReceptionStatus::Completed, 'completed_at' => now(), 'received_at' => now()]);
            $this->unsetRelation('lines');
        });

        return $this->notifyDiscrepancies();
    }

    /**
     * Lignes manquantes (annoncées par le fournisseur, mais non reçues) de la réception.
     *
     * @return Collection<int, ReceptionLine>
     */
    public function shortLines(): Collection
    {
        return $this->lines->filter(fn (ReceptionLine $line): bool => $line->quantity_short > 0)->values();
    }

    /**
     * Articles reçus en trop de la réception.
     *
     * @return Collection<int, ReceptionLine>
     */
    public function overshipLines(): Collection
    {
        return $this->lines->filter(fn (ReceptionLine $line): bool => $line->orderLine->is_overship)->values();
    }

    /**
     * Avise le fournisseur des articles manquants (demande de crédit) et reçus en trop, au courriel de commande (ou
     * général) du fournisseur. Rien n'est envoyé sans écart ni quand les courriels aux fournisseurs sont désactivés.
     *
     * @return bool Faux seulement si un courriel devait partir mais n'a pas pu être envoyé.
     */
    public function notifyDiscrepancies(): bool
    {
        $this->load(['supplier', 'lines.orderLine.order', 'lines.orderLine.product']);

        if (($this->shortLines()->isEmpty() && $this->overshipLines()->isEmpty()) || ! SupplierOrder::emailEnabled()) {
            return true;
        }

        $recipient = $this->supplier->order_email ?: $this->supplier->email;

        if (blank($recipient)) {
            return false;
        }

        try {
            Mail::to($recipient)->send(new ReceptionDiscrepancyReported($this));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Abandonne une réception en cours (aucun effet sur l'inventaire).
     */
    public function discard(): void
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Une réception terminée ne peut pas être abandonnée : renversez ses lignes.'));
        }

        DB::transaction(function (): void {
            $this->refreshForUpdate();

            if (! $this->isInProgress()) {
                throw new DomainException(__('Une réception terminée ne peut pas être abandonnée : renversez ses lignes.'));
            }

            $overshipLines = $this->lines()->with('orderLine.order')->get()
                ->map(fn (ReceptionLine $line) => $line->orderLine)
                ->filter(fn (SupplierOrderLine $orderLine) => $orderLine->is_overship);

            $this->delete();

            $overshipLines->each(fn (SupplierOrderLine $orderLine) => $orderLine->order->removeOvershipLine($orderLine));
        });
    }

    /**
     * Enregistre et termine une réception d'un seul coup (réception depuis une commande).
     *
     * @param  array<int, array{quantity: int|string, damaged?: int|string|null}>  $receipts  Indexé par id de ligne de commande.
     * @param  array{reference?: string|null, notes?: string|null, received_by?: int|string|null}  $details
     */
    public static function record(Supplier $supplier, array $receipts, array $details = []): Reception
    {
        return DB::transaction(function () use ($supplier, $receipts, $details): Reception {
            $reception = static::start($supplier, $receipts, $details);
            $reception->complete();

            return $reception;
        });
    }

    private function guardInProgress(ReceptionLine $line): void
    {
        if (! $this->isInProgress() || $line->reception_id !== $this->id) {
            throw new DomainException(__('Cette ligne ne peut plus être modifiée.'));
        }
    }
}
