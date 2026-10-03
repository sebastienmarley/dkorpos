<?php

namespace App\Models;

use App\Enums\SupplierType;
use Database\Factories\ReceptionFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $number
 * @property int $supplier_id
 * @property int|null $received_by
 * @property Carbon $received_at
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read User|null $receiver
 * @property-read Collection<int, ReceptionLine> $lines
 * @property-read float $total
 */
#[Fillable(['number', 'supplier_id', 'received_by', 'received_at', 'reference', 'notes'])]
class Reception extends Model
{
    /** @use HasFactory<ReceptionFactory> */
    use HasFactory;

    protected $casts = [
        'received_at' => 'datetime',
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

    public function getTotalAttribute(): float
    {
        return round($this->lines->sum(fn (ReceptionLine $line) => $line->total), 2);
    }

    /**
     * Enregistre une réception d'un fournisseur couvrant une ou plusieurs de ses commandes. Les quantités sont
     * plafonnées à ce qui reste à recevoir; le coût réel saisi remplace le coût commandé.
     *
     * @param  array<int, array{quantity: int|string, unit_cost?: float|int|string|null}>  $receipts  Indexé par id de ligne de commande.
     * @param  array{reference?: string|null, notes?: string|null, received_by?: int|string|null}  $details
     */
    public static function record(Supplier $supplier, array $receipts, array $details = []): Reception
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
                'received_by' => $details['received_by'] ?? null,
                'received_at' => now(),
                'reference' => filled($details['reference'] ?? null) ? $details['reference'] : null,
                'notes' => filled($details['notes'] ?? null) ? $details['notes'] : null,
            ]);

            foreach ($lines->groupBy('supplier_order_id') as $orderLines) {
                $orderLines->first()->order->applyReceipts($reception, $orderLines, $receipts);
            }

            if ($reception->lines()->doesntExist()) {
                throw new DomainException(__('Saisissez au moins une quantité à réceptionner.'));
            }

            return $reception;
        });
    }
}
