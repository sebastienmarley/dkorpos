<?php

namespace App\Models;

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
     * annoncée par le fournisseur, mais non reçue.
     *
     * @param  array<int, array{quantity: int|string, damaged?: int|string|null, short?: int|string|null}>  $receipts  Indexé par id de ligne de commande.
     * @param  array{reference?: string|null, notes?: string|null, received_by?: int|string|null}  $details
     */
    public static function start(Supplier $supplier, array $receipts, array $details = []): Reception
    {
        $receipts = array_filter($receipts, fn (array $receipt) => (int) $receipt['quantity'] + (int) ($receipt['short'] ?? 0) > 0);

        if ($receipts === []) {
            throw new DomainException(__('Saisissez au moins une quantité à réceptionner.'));
        }

        return DB::transaction(function () use ($supplier, $receipts, $details): Reception {
            $lines = SupplierOrderLine::query()
                ->with('order')
                ->whereIn('id', array_keys($receipts))
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
            $receiverStoreId = isset($details['received_by']) ? User::query()->whereKey($details['received_by'])->value('store_id') : null;

            if ($receiverStoreId !== null && $receiverStoreId !== $storeId) {
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

                if ($quantity + $short > 0) {
                    $reception->lines()->create([
                        'supplier_order_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'quantity' => $quantity,
                        'quantity_damaged' => self::damagedQuantity($line, $quantity, $receipts[$line->id]['damaged'] ?? 0),
                        'quantity_short' => $short,
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
     * Change les quantités d'une ligne d'une réception en cours : reçue, dont endommagée, et manquante (annoncée par
     * le fournisseur, mais non reçue). Reçu et manquant ensemble vont de 1 à ce qui reste à recevoir. Pour un article
     * reçu en trop, la quantité de la ligne de commande suit et il n'y a pas de manquant.
     *
     * @throws DomainException si une quantité est invalide.
     */
    public function setLineQuantity(ReceptionLine $line, int $quantity, ?int $damaged = null, ?int $short = null): void
    {
        DB::transaction(function () use ($line, $quantity, $damaged, $short): void {
            $this->refreshForUpdate();
            $this->guardInProgress($line);
            $this->applyLineQuantity($line, $quantity, $damaged, $short);
        });
    }

    private function applyLineQuantity(ReceptionLine $line, int $quantity, ?int $damaged, ?int $short): void
    {
        $orderLine = $line->orderLine;
        $short = $orderLine->is_overship ? 0 : ($short ?? $line->quantity_short);

        if ($orderLine->is_overship && $quantity !== $orderLine->quantity) {
            if ($quantity < 1) {
                throw new DomainException(__('La quantité reçue en trop doit être d\'au moins 1.'));
            }

            $orderLine->order->updateLine($orderLine, $quantity, Money::zero());
            $orderLine->refresh();
        }

        $outstanding = $orderLine->quantity_outstanding;

        if ($quantity < 0 || $short < 0 || $quantity + $short < 1 || $quantity + $short > $outstanding) {
            throw new DomainException(__('La quantité reçue et la quantité manquante doivent totaliser entre 1 et :max.', ['max' => $outstanding]));
        }

        $line->update([
            'quantity' => $quantity,
            'quantity_damaged' => self::damagedQuantity($orderLine, $quantity, $damaged ?? $line->quantity_damaged),
            'quantity_short' => $short,
        ]);
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
