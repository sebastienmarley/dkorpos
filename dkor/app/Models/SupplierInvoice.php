<?php

namespace App\Models;

use App\Enums\ReceptionStatus;
use Database\Factories\SupplierInvoiceFactory;
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
 * Facture d'un fournisseur pour une réception terminée: coûts réels des lignes, frais de transport, de douanes et
 * taxes, comparés au montant de la facture saisi.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $reception_id
 * @property string $invoice_number
 * @property Carbon $invoice_date
 * @property float $merchandise_total
 * @property float $freight_fee
 * @property float $customs_fee
 * @property float $taxes
 * @property float $computed_total
 * @property float $invoice_total
 * @property float $variance
 * @property float $discount_percent
 * @property int|null $discount_days
 * @property bool $discount_next_month
 * @property Carbon|null $discount_due_date
 * @property float $discount_amount
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Reception $reception
 * @property-read Collection<int, SupplierInvoiceLine> $lines
 */
#[Fillable([
    'supplier_id', 'reception_id', 'invoice_number', 'invoice_date', 'merchandise_total', 'freight_fee', 'customs_fee',
    'taxes', 'computed_total', 'invoice_total', 'variance', 'discount_percent', 'discount_days', 'discount_next_month', 'discount_due_date',
    'discount_amount', 'created_by',
])]
class SupplierInvoice extends Model
{
    /** @use HasFactory<SupplierInvoiceFactory> */
    use HasFactory;

    protected $casts = [
        'invoice_date' => 'date',
        'merchandise_total' => 'float',
        'freight_fee' => 'float',
        'customs_fee' => 'float',
        'taxes' => 'float',
        'computed_total' => 'float',
        'invoice_total' => 'float',
        'variance' => 'float',
        'discount_percent' => 'float',
        'discount_days' => 'integer',
        'discount_next_month' => 'boolean',
        'discount_due_date' => 'date',
        'discount_amount' => 'float',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /** @return HasMany<SupplierInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('id');
    }

    public function hasVariance(): bool
    {
        return abs($this->variance) >= 0.005;
    }

    /**
     * Totaux d'une facture: marchandises aux coûts réels, total calculé avec les frais et taxes, écart avec le
     * montant de la facture, et escompte de paiement rapide du fournisseur.
     *
     * @param  Collection<int, ReceptionLine>  $lines  Lignes de réception à facturer.
     * @param  array<int, float|int|string>  $unitCosts  Coût réel par id de ligne de réception (sinon le coût actuel).
     * @return array{merchandise_total: float, computed_total: float, variance: float, discount_percent: float, discount_days: int|null, discount_next_month: bool, discount_due_date: Carbon|null, discount_amount: float}
     */
    public static function totals(Supplier $supplier, Collection $lines, array $unitCosts, float $freight, float $customs, float $taxes, float $invoiceTotal, ?Carbon $invoiceDate = null): array
    {
        $merchandise = round($lines->sum(fn (ReceptionLine $line) => $line->quantity_net * (float) ($unitCosts[$line->id] ?? $line->unit_cost)), 2);
        $computed = round($merchandise + $freight + $customs + $taxes, 2);

        $percent = (float) $supplier->early_payment_discount_percent;
        $days = $supplier->early_payment_discount_days;
        $nextMonth = (bool) $supplier->early_payment_next_month;
        $hasDiscount = $percent > 0 && $days !== null;

        return [
            'merchandise_total' => $merchandise,
            'computed_total' => $computed,
            'variance' => round($invoiceTotal - $computed, 2),
            'discount_percent' => $percent,
            'discount_days' => $days,
            'discount_next_month' => $nextMonth,
            'discount_due_date' => $hasDiscount && $invoiceDate ? static::discountDueDate($invoiceDate, $days, $nextMonth) : null,
            'discount_amount' => $hasDiscount ? round($invoiceTotal * $percent / 100, 2) : 0.0,
        ];
    }

    /**
     * Échéance de l'escompte: N jours après la date de facturation, ou (mois suivant) le jour N du mois suivant
     * (ramené au dernier jour du mois s'il n'existe pas, ex.: le 31).
     */
    public static function discountDueDate(Carbon $invoiceDate, int $days, bool $nextMonth): Carbon
    {
        if (! $nextMonth) {
            return $invoiceDate->copy()->addDays($days);
        }

        $month = $invoiceDate->copy()->startOfMonth()->addMonthNoOverflow();

        return $month->setDay(min(max($days, 1), $month->daysInMonth));
    }

    /**
     * Enregistre la facture d'une réception terminée. Les coûts réels sont reportés sur les lignes de la
     * réception, de la commande (pour les prochaines réceptions) et sur les unités d'inventaire reçues; les
     * commandes entièrement reçues et facturées passent à « Facturée ».
     *
     * @param  array{invoice_number: string, invoice_date: string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: float|int|string, invoice_total: float|int|string}  $details
     * @param  array<int, float|int|string>  $unitCosts  Coût réel par id de ligne de réception.
     */
    public static function record(Reception $reception, array $details, array $unitCosts = [], int|string|null $createdBy = null): SupplierInvoice
    {
        if ($reception->status !== ReceptionStatus::Completed) {
            throw new DomainException(__('Seule une réception terminée peut être facturée.'));
        }

        if ($reception->invoice()->exists()) {
            throw new DomainException(__('Cette réception est déjà facturée.'));
        }

        return DB::transaction(function () use ($reception, $details, $unitCosts, $createdBy): SupplierInvoice {
            $lines = $reception->invoiceableLines()->with('orderLine')->get();

            if ($lines->isEmpty()) {
                throw new DomainException(__('Cette réception n\'a rien à facturer.'));
            }

            $supplier = $reception->supplier;
            $date = Carbon::parse($details['invoice_date']);
            $totals = static::totals(
                $supplier,
                $lines,
                $unitCosts,
                (float) ($details['freight_fee'] ?? 0),
                (float) ($details['customs_fee'] ?? 0),
                (float) ($details['taxes'] ?? 0),
                (float) $details['invoice_total'],
                $date,
            );

            $invoice = static::create([
                'supplier_id' => $supplier->id,
                'reception_id' => $reception->id,
                'invoice_number' => $details['invoice_number'],
                'invoice_date' => $date,
                'freight_fee' => round((float) ($details['freight_fee'] ?? 0), 2),
                'customs_fee' => round((float) ($details['customs_fee'] ?? 0), 2),
                'taxes' => round((float) ($details['taxes'] ?? 0), 2),
                'invoice_total' => round((float) $details['invoice_total'], 2),
                'created_by' => $createdBy,
                ...$totals,
            ]);

            foreach ($lines as $line) {
                $cost = round((float) ($unitCosts[$line->id] ?? $line->unit_cost), 2);

                $invoice->lines()->create(['reception_line_id' => $line->id, 'quantity' => $line->quantity_net, 'unit_cost' => $cost]);

                $line->update(['unit_cost' => $cost]);
                $line->orderLine->update(['unit_cost' => $cost]);
                InventoryUnit::where('reception_line_id', $line->id)->update(['cost' => $cost]);
            }

            SupplierOrder::query()
                ->whereIn('id', $lines->pluck('orderLine.supplier_order_id')->unique())
                ->get()
                ->each(fn (SupplierOrder $order) => $order->markInvoicedIfSettled());

            return $invoice;
        });
    }
}
