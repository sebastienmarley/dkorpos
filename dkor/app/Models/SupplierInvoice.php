<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\DeliveryMethod;
use App\Enums\ReceptionStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierType;
use App\Support\Money;
use Carbon\CarbonInterface;
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
use Illuminate\Support\Str;

/**
 * Facture d'un fournisseur pour une réception terminée: coûts réels des lignes, frais de transport, de douanes et
 * taxes, comparés au montant de la facture saisi.
 *
 * @property int $id
 * @property int $supplier_id
 * @property int|null $reception_id
 * @property int|null $supplier_order_id
 * @property string $invoice_number
 * @property Carbon $invoice_date
 * @property string|null $description
 * @property Money $merchandise_total
 * @property Money $freight_fee
 * @property Money $customs_fee
 * @property Money $taxes
 * @property Money $computed_total
 * @property Money $invoice_total
 * @property Money $variance
 * @property float $discount_percent
 * @property int|null $discount_days
 * @property bool $discount_next_month
 * @property Carbon|null $discount_due_date
 * @property Money $discount_amount
 * @property Money $credit_requested Demande de crédit au fournisseur pour les articles facturés mais manquants à la réception.
 * @property string|null $credit_note_number Numéro de la note de crédit du fournisseur, une fois reçue.
 * @property Carbon|null $credit_received_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Reception|null $reception
 * @property-read SupplierOrder|null $order
 * @property-read Collection<int, SupplierInvoiceLine> $lines
 * @property-read Collection<int, SupplierInvoiceTax> $taxLines
 * @property-read Collection<int, CustomerOrderDelivery> $shipments
 */
#[Fillable([
    'supplier_id', 'reception_id', 'supplier_order_id', 'invoice_number', 'invoice_date', 'description', 'merchandise_total', 'freight_fee', 'customs_fee',
    'taxes', 'computed_total', 'invoice_total', 'variance', 'discount_percent', 'discount_days', 'discount_next_month', 'discount_due_date',
    'discount_amount', 'credit_requested', 'credit_note_number', 'credit_received_at', 'created_by',
])]
class SupplierInvoice extends Model
{
    /** @use HasFactory<SupplierInvoiceFactory> */
    use HasFactory;

    protected $attributes = [
        'credit_requested' => 0,
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'merchandise_total' => MoneyCast::class,
        'freight_fee' => MoneyCast::class,
        'customs_fee' => MoneyCast::class,
        'taxes' => MoneyCast::class,
        'computed_total' => MoneyCast::class,
        'invoice_total' => MoneyCast::class,
        'variance' => MoneyCast::class,
        'discount_percent' => 'float',
        'discount_days' => 'integer',
        'discount_next_month' => 'boolean',
        'discount_due_date' => 'date',
        'discount_amount' => MoneyCast::class,
        'credit_requested' => MoneyCast::class,
        'credit_received_at' => 'date',
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

    /** @return BelongsTo<SupplierOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    /** @return HasMany<SupplierInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('id');
    }

    /** @return HasMany<SupplierInvoiceTax, $this> */
    public function taxLines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceTax::class)->orderBy('id');
    }

    /**
     * Envois (expéditions) d'un transporteur couverts par cette facture.
     *
     * @return HasMany<CustomerOrderDelivery, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(CustomerOrderDelivery::class)->orderBy('shipped_at')->orderBy('id');
    }

    public function hasVariance(): bool
    {
        return ! $this->variance->isZero();
    }

    /** Une demande de crédit attend la note de crédit du fournisseur. */
    public function isCreditPending(): bool
    {
        return $this->credit_requested->isPositive() && $this->credit_received_at === null;
    }

    /** Montant de la facture moins la demande de crédit : ce qui est réellement dû pour ce qui a été reçu. */
    public function netTotal(): Money
    {
        return $this->invoice_total->minus($this->credit_requested);
    }

    /**
     * Enregistre la note de crédit reçue du fournisseur pour les manquants de cette facture.
     *
     * @throws DomainException si la facture n'a pas de demande de crédit en attente.
     */
    public function recordCreditNote(string $creditNoteNumber, CarbonInterface $receivedAt): void
    {
        if (! $this->isCreditPending()) {
            throw new DomainException(__('Cette facture n\'a pas de demande de crédit en attente.'));
        }

        if (blank($creditNoteNumber)) {
            throw new DomainException(__('Indiquez le numéro de la note de crédit du fournisseur.'));
        }

        $this->update(['credit_note_number' => trim($creditNoteNumber), 'credit_received_at' => $receivedAt]);
    }

    /**
     * Totaux d'une facture: marchandises facturées (reçues et manquantes) aux coûts réels, total calculé avec les frais
     * et taxes, écart avec le montant de la facture, escompte de paiement rapide du fournisseur, et demande de crédit
     * pour les manquants (facturés, mais non reçus).
     *
     * @param  \Illuminate\Support\Collection<int, ReceptionLine>|\Illuminate\Support\Collection<int, SupplierOrderLine>  $lines  Lignes à facturer (réception ou service).
     * @param  array<int, Money>  $unitCosts  Coût réel par id de ligne (sinon le coût actuel).
     * @return array{merchandise_total: Money, computed_total: Money, variance: Money, discount_percent: float, discount_days: int|null, discount_next_month: bool, discount_due_date: Carbon|null, discount_amount: Money, credit_requested: Money}
     */
    public static function totals(Supplier $supplier, \Illuminate\Support\Collection $lines, array $unitCosts, Money $freight, Money $customs, Money $taxes, Money $invoiceTotal, ?Carbon $invoiceDate = null, ?Money $merchandise = null): array
    {
        $merchandise ??= Money::sum($lines->map(fn (ReceptionLine|SupplierOrderLine $line): Money => ($unitCosts[$line->id] ?? $line->unit_cost)->times($line->billable_quantity)));
        $computed = Money::sum([$merchandise, $freight, $customs, $taxes]);

        $percent = (float) $supplier->early_payment_discount_percent;
        $days = $supplier->early_payment_discount_days;
        $nextMonth = (bool) $supplier->early_payment_next_month;
        $hasDiscount = $percent > 0 && $days !== null;

        return [
            'merchandise_total' => $merchandise,
            'computed_total' => $computed,
            'variance' => $invoiceTotal->minus($computed),
            'discount_percent' => $percent,
            'discount_days' => $days,
            'discount_next_month' => $nextMonth,
            'discount_due_date' => $hasDiscount && $invoiceDate ? static::discountDueDate($invoiceDate, $days, $nextMonth) : null,
            'discount_amount' => $hasDiscount ? $invoiceTotal->percent($percent) : Money::zero(),
            'credit_requested' => Money::sum($lines->map(fn (ReceptionLine|SupplierOrderLine $line): Money => ($unitCosts[$line->id] ?? $line->unit_cost)->times($line->quantity_short))),
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

            $invoice = self::persist($reception->supplier, ['reception_id' => $reception->id], $lines, $details, $unitCosts, $createdBy);

            foreach ($lines as $line) {
                $cost = $invoice->lines->firstWhere('reception_line_id', $line->id)->unit_cost;

                $line->update(['unit_cost' => $cost]);
                $line->orderLine->update(['unit_cost' => $cost]);
                InventoryUnit::where('reception_line_id', $line->id)->update(['cost' => $cost->cents()]);
            }

            SupplierOrder::query()
                ->whereIn('id', $lines->pluck('orderLine.supplier_order_id')->unique())
                ->get()
                ->each(fn (SupplierOrder $order) => $order->markInvoicedIfSettled());

            return $invoice;
        });
    }

    /**
     * Enregistre la facture d'une commande de services complétée (pas de réception): les coûts réels remplacent
     * ceux de la commande et la commande passe à « Facturée ».
     *
     * @param  array{invoice_number: string, invoice_date: string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: float|int|string, invoice_total: float|int|string}  $details
     * @param  array<int, float|int|string>  $unitCosts  Coût réel par id de ligne de commande.
     */
    public static function recordForOrder(SupplierOrder $order, array $details, array $unitCosts = [], int|string|null $createdBy = null): SupplierInvoice
    {
        if ($order->type !== SupplierType::Service || $order->status !== SupplierOrderStatus::Received) {
            throw new DomainException(__('Seule une commande de services complétée peut être facturée ici.'));
        }

        if ($order->supplierInvoice()->exists()) {
            throw new DomainException(__('Cette commande est déjà facturée.'));
        }

        return DB::transaction(function () use ($order, $details, $unitCosts, $createdBy): SupplierInvoice {
            $lines = $order->lines()->get()->reject(fn (SupplierOrderLine $line) => $line->status->isClosed())->values();

            if ($lines->isEmpty()) {
                throw new DomainException(__('Cette commande n\'a rien à facturer.'));
            }

            $invoice = self::persist($order->supplier, ['supplier_order_id' => $order->id], $lines, $details, $unitCosts, $createdBy);

            foreach ($lines as $line) {
                $line->update(['unit_cost' => $invoice->lines->firstWhere('supplier_order_line_id', $line->id)->unit_cost]);
            }

            $order->update(['status' => SupplierOrderStatus::Invoiced]);

            return $invoice;
        });
    }

    /**
     * Enregistre une facture rattachée à un fournisseur seulement, sans réception ni commande: un montant de
     * marchandises ou de services, les frais et taxes, comparés au montant de la facture. Aucun effet sur
     * l'inventaire ni sur les commandes.
     *
     * @param  array{invoice_number: string, invoice_date: string, description?: string|null, merchandise_total: float|int|string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: float|int|string, invoice_total: float|int|string}  $details
     */
    public static function recordStandalone(Supplier $supplier, array $details, int|string|null $createdBy = null): SupplierInvoice
    {
        return DB::transaction(fn () => self::persist($supplier, [], collect(), $details, [], $createdBy));
    }

    /**
     * Enregistre la facture d'un transporteur qui couvre plusieurs envois (issue #95) : chaque envoi y est relié (une
     * seule fois), les taxes sont détaillées, et l'écriture est passée au grand livre. Le montant avant taxes va aux
     * frais d'expédition et transport, chaque taxe à son compte « à recevoir », et le total aux comptes fournisseurs.
     *
     * @param  list<int>  $shipmentIds  envois (expéditions envoyées) couverts par la facture
     * @param  array{invoice_number: string, invoice_date: string, description?: string|null, merchandise_total: float|int|string, invoice_total: float|int|string}  $details
     * @param  array<string, float|int|string>  $taxAmounts  montant par taxe (TPS, TVQ…), en dollars
     *
     * @throws DomainException si le fournisseur n'est pas un transporteur, si un envoi n'est pas de lui, n'est pas
     *                         encore parti ou est déjà facturé, ou si le montant avant taxes n'est pas positif.
     */
    public static function recordForShipments(Supplier $carrier, array $shipmentIds, array $details, array $taxAmounts, int|string|null $createdBy = null): SupplierInvoice
    {
        if ($carrier->type !== SupplierType::Shipping) {
            throw new DomainException(__('Seul un fournisseur d\'expédition facture des envois.'));
        }

        $shipmentIds = array_values(array_unique($shipmentIds));

        if ($shipmentIds === []) {
            throw new DomainException(__('Choisissez au moins un envoi couvert par la facture.'));
        }

        return DB::transaction(function () use ($carrier, $shipmentIds, $details, $taxAmounts, $createdBy): SupplierInvoice {
            $shipments = CustomerOrderDelivery::query()->whereKey($shipmentIds)->lockForUpdate()->get();

            if ($shipments->count() !== count($shipmentIds)) {
                throw new DomainException(__('Un envoi choisi n\'existe plus.'));
            }

            foreach ($shipments as $shipment) {
                if ($shipment->method !== DeliveryMethod::Shipping || $shipment->shipping_supplier_id !== $carrier->id || $shipment->shipped_at === null) {
                    throw new DomainException(__('L\'envoi de la commande #:order n\'est pas un envoi de ce transporteur.', ['order' => $shipment->customer_order_id]));
                }

                if ($shipment->supplier_invoice_id !== null) {
                    throw new DomainException(__('L\'envoi de la commande #:order est déjà relié à une facture.', ['order' => $shipment->customer_order_id]));
                }
            }

            $taxes = collect($taxAmounts)
                ->map(fn (float|int|string $amount): Money => Money::parse($amount))
                ->filter(fn (Money $amount): bool => $amount->isPositive());
            $taxTotal = Money::sum($taxes->values()->all());

            $invoice = self::persist($carrier, [], collect(), [...$details, 'freight_fee' => 0, 'customs_fee' => 0, 'taxes' => $taxTotal->toDecimal()], [], $createdBy);
            $expense = $invoice->invoice_total->minus($taxTotal);

            if (! $expense->isPositive()) {
                throw new DomainException(__('Le montant de la facture doit dépasser ses taxes.'));
            }

            foreach ($taxes as $taxName => $amount) {
                $invoice->taxLines()->create(['tax_name' => $taxName, 'amount' => $amount]);
            }

            CustomerOrderDelivery::query()->whereKey($shipmentIds)->update(['supplier_invoice_id' => $invoice->id]);

            LedgerEntry::post(
                Str::limit(__('Facture :number — :carrier (:count envois)', ['number' => $invoice->invoice_number, 'carrier' => $carrier->name, 'count' => count($shipmentIds)]), 250),
                [
                    ['account' => LedgerAccount::forKey(LedgerAccount::SHIPPING_EXPENSE), 'debit' => $expense],
                    ...$taxes->map(fn (Money $amount, string $taxName): array => ['account' => LedgerAccount::forTaxReceivable($taxName), 'debit' => $amount])->values()->all(),
                    ['account' => LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE), 'credit' => $invoice->invoice_total],
                ],
                $invoice,
                $invoice->invoice_date,
                $createdBy,
            );

            return $invoice;
        });
    }

    /**
     * Somme des coûts soumis par le transporteur pour les envois couverts par la facture.
     */
    public function shipmentsCost(): Money
    {
        return Money::sum($this->shipments->map(fn (CustomerOrderDelivery $shipment): Money => $shipment->shipping_cost ?? Money::zero())->all());
    }

    /**
     * Écart entre le montant avant taxes de la facture et les coûts soumis de ses envois (positif : facturé de plus).
     */
    public function shipmentsVariance(): Money
    {
        return $this->merchandise_total->minus($this->shipmentsCost());
    }

    /**
     * Crée la facture et ses lignes (quantités et coûts réels) à partir des lignes à facturer.
     *
     * @param  array<string, int>  $target  Réception ou commande facturée.
     * @param  \Illuminate\Support\Collection<int, ReceptionLine>|\Illuminate\Support\Collection<int, SupplierOrderLine>  $lines
     * @param  array<string, mixed>  $details
     *                                         Les montants de $details et de $unitCosts sont en dollars, tels que saisis.
     * @param  array<int, float|int|string>  $unitCosts
     */
    private static function persist(Supplier $supplier, array $target, \Illuminate\Support\Collection $lines, array $details, array $unitCosts, int|string|null $createdBy): SupplierInvoice
    {
        $date = Carbon::parse($details['invoice_date']);
        $amounts = [
            'freight_fee' => Money::parse($details['freight_fee'] ?? 0),
            'customs_fee' => Money::parse($details['customs_fee'] ?? 0),
            'taxes' => Money::parse($details['taxes'] ?? 0),
            'invoice_total' => Money::parse($details['invoice_total']),
        ];
        $costs = array_map(fn (float|int|string $cost): Money => Money::parse($cost), $unitCosts);
        $totals = static::totals(
            $supplier,
            $lines,
            $costs,
            $amounts['freight_fee'],
            $amounts['customs_fee'],
            $amounts['taxes'],
            $amounts['invoice_total'],
            $date,
            isset($details['merchandise_total']) ? Money::parse($details['merchandise_total']) : null,
        );

        $invoice = static::create([
            'supplier_id' => $supplier->id,
            ...$target,
            'invoice_number' => $details['invoice_number'],
            'invoice_date' => $date,
            'description' => filled($details['description'] ?? null) ? $details['description'] : null,
            ...$amounts,
            'created_by' => $createdBy,
            ...$totals,
        ]);

        foreach ($lines as $line) {
            $invoice->lines()->create([
                ($line instanceof ReceptionLine ? 'reception_line_id' : 'supplier_order_line_id') => $line->id,
                'quantity' => $line->billable_quantity,
                'quantity_short' => $line->quantity_short,
                'unit_cost' => $costs[$line->id] ?? $line->unit_cost,
            ]);
        }

        return $invoice->load('lines');
    }
}
