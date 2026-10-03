<?php

namespace App\Models;

use App\Enums\ReceptionStatus;
use App\Enums\SupplierType;
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

/**
 * @property int $id
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
 * @property-read float $total
 */
#[Fillable(['number', 'supplier_id', 'status', 'received_by', 'received_at', 'completed_at', 'reference', 'notes'])]
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
     * Lignes de cette réception encore comptées après renversements (à facturer).
     *
     * @return HasMany<ReceptionLine, $this>
     */
    public function invoiceableLines(): HasMany
    {
        return $this->lines()->whereColumn('quantity', '>', 'quantity_reversed');
    }

    public function getTotalAttribute(): float
    {
        return round($this->lines->sum(fn (ReceptionLine $line) => $line->total), 2);
    }

    public function isInProgress(): bool
    {
        return $this->status === ReceptionStatus::InProgress;
    }

    /**
     * Commence une réception: elle contient les lignes de commande choisies et leur quantité, sans effet sur
     * l'inventaire tant qu'elle n'est pas terminée. Les quantités sont plafonnées à ce qui reste à recevoir.
     *
     * @param  array<int, array{quantity: int|string}>  $receipts  Indexé par id de ligne de commande.
     * @param  array{reference?: string|null, notes?: string|null, received_by?: int|string|null}  $details
     */
    public static function start(Supplier $supplier, array $receipts, array $details = []): Reception
    {
        $receipts = array_filter($receipts, fn (array $receipt) => (int) $receipt['quantity'] > 0);

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

            $reception = static::create([
                'supplier_id' => $supplier->id,
                'status' => ReceptionStatus::InProgress,
                'received_by' => $details['received_by'] ?? null,
                'received_at' => now(),
                'reference' => filled($details['reference'] ?? null) ? $details['reference'] : null,
                'notes' => filled($details['notes'] ?? null) ? $details['notes'] : null,
            ]);

            foreach ($lines as $line) {
                $quantity = min((int) $receipts[$line->id]['quantity'], $line->quantity_outstanding);

                if ($quantity > 0) {
                    $reception->lines()->create([
                        'supplier_order_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'quantity' => $quantity,
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
     * Change la quantité à recevoir d'une ligne d'une réception en cours (1 à ce qui reste à recevoir).
     */
    public function setLineQuantity(ReceptionLine $line, int $quantity): void
    {
        $this->guardInProgress($line);

        $outstanding = $line->orderLine->quantity_outstanding;

        if ($quantity < 1 || $quantity > $outstanding) {
            throw new DomainException(__('La quantité doit être entre 1 et :max.', ['max' => $outstanding]));
        }

        $line->update(['quantity' => $quantity]);
    }

    /**
     * Retire une ligne d'une réception en cours.
     */
    public function removeLine(ReceptionLine $line): void
    {
        $this->guardInProgress($line);

        $line->delete();
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
     */
    public function complete(): void
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Cette réception est déjà terminée.'));
        }

        DB::transaction(function (): void {
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
    }

    /**
     * Abandonne une réception en cours (aucun effet sur l'inventaire).
     */
    public function discard(): void
    {
        if (! $this->isInProgress()) {
            throw new DomainException(__('Une réception terminée ne peut pas être abandonnée : renversez ses lignes.'));
        }

        $this->delete();
    }

    /**
     * Enregistre et termine une réception d'un seul coup (réception depuis une commande).
     *
     * @param  array<int, array{quantity: int|string}>  $receipts  Indexé par id de ligne de commande.
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
