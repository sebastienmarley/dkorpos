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
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $ledger_account_id Compte de charge d'une facture sans réception ni commande (ADR 0016).
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
 * @property string $exchange_rate Taux de la devise du fournisseur, figé à la saisie : l'écriture est en CAD (ADR 0016).
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
 * @property Carbon|null $paid_outside_at Payée hors de l'application, avant la tenue des paiements (#132).
 * @property int|null $paid_outside_by
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
    'supplier_id', 'ledger_account_id', 'reception_id', 'supplier_order_id', 'invoice_number', 'invoice_date', 'description', 'merchandise_total', 'freight_fee', 'customs_fee',
    'taxes', 'computed_total', 'invoice_total', 'variance', 'discount_percent', 'discount_days', 'discount_next_month', 'discount_due_date',
    'discount_amount', 'credit_requested', 'credit_note_number', 'credit_received_at', 'created_by', 'exchange_rate', 'paid_outside_at', 'paid_outside_by',
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
        'exchange_rate' => 'decimal:6',
        'variance' => MoneyCast::class,
        'discount_percent' => 'float',
        'discount_days' => 'integer',
        'discount_next_month' => 'boolean',
        'discount_due_date' => 'date',
        'discount_amount' => MoneyCast::class,
        'credit_requested' => MoneyCast::class,
        'credit_received_at' => 'date',
        'paid_outside_at' => 'datetime',
    ];

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

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

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /**
     * Factures qui ont encore un solde dû (#132) : le montant net dépasse ce que les paiements non annulés ont soldé.
     *
     * @param  Builder<SupplierInvoice>  $query
     * @return Builder<SupplierInvoice>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('supplier_invoices.paid_outside_at')->whereRaw('supplier_invoices.invoice_total - supplier_invoices.credit_requested > (
            select coalesce(sum(allocations.amount + allocations.discount), 0)
            from supplier_payment_allocations as allocations
            join supplier_payments as payments on payments.id = allocations.supplier_payment_id
            where allocations.supplier_invoice_id = supplier_invoices.id and payments.cancelled_at is null
        )');
    }

    /**
     * Allocations des paiements non annulés.
     *
     * @return \Illuminate\Support\Collection<int, SupplierPaymentAllocation>
     */
    public function activeAllocations(): \Illuminate\Support\Collection
    {
        return $this->paymentAllocations->loadMissing('payment')->reject(fn (SupplierPaymentAllocation $allocation): bool => $allocation->payment->isCancelled())->values()->toBase();
    }

    /** Ce qui reste à payer : le montant net moins les paiements et escomptes appliqués (devise du fournisseur). */
    public function balanceDue(): Money
    {
        if ($this->paid_outside_at !== null) {
            return Money::zero();
        }

        return $this->netTotal()->minus(Money::sum($this->activeAllocations()->map(fn (SupplierPaymentAllocation $allocation): Money => $allocation->settled())->all()));
    }

    public function isPaid(): bool
    {
        return ! $this->balanceDue()->isPositive();
    }

    /** La facture a une écriture au grand livre : elle se paie par un paiement, qui solde les comptes fournisseurs. */
    public function hasLedgerEntry(): bool
    {
        return LedgerEntry::query()->whereMorphedTo('reference', $this)->exists();
    }

    /**
     * Marque payée hors de l'application une facture d'avant la tenue des paiements (#132) : elle n'a pas d'écriture,
     * donc rien à solder aux comptes fournisseurs.
     *
     * @throws DomainException si la facture a une écriture, un paiement, ou si elle est déjà payée.
     */
    public function markPaidOutside(?User $user = null): void
    {
        if ($this->hasLedgerEntry()) {
            throw new DomainException(__('La facture :number est au grand livre : enregistrez son paiement.', ['number' => $this->invoice_number]));
        }

        if ($this->isPaid() || $this->activeAllocations()->isNotEmpty()) {
            throw new DomainException(__('La facture :number a déjà un paiement.', ['number' => $this->invoice_number]));
        }

        $this->update(['paid_outside_at' => now(), 'paid_outside_by' => $user?->id]);
    }

    /** L'escompte de paiement rapide s'applique encore à un paiement fait à cette date. */
    public function discountAvailableOn(CarbonInterface $date): bool
    {
        return $this->discount_amount->isPositive()
            && $this->discount_due_date !== null
            && $date->toDateString() <= $this->discount_due_date->toDateString()
            && $this->activeAllocations()->every(fn (SupplierPaymentAllocation $allocation): bool => $allocation->discount->isZero());
    }

    /** Montant net dû aux comptes fournisseurs, en CAD au taux de la facture. */
    public function payableCad(): Money
    {
        return $this->invoice_total->multiply($this->exchange_rate)->minus($this->credit_requested->multiply($this->exchange_rate));
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

        DB::transaction(function () use ($creditNoteNumber, $receivedAt): void {
            $this->update(['credit_note_number' => trim($creditNoteNumber), 'credit_received_at' => $receivedAt]);

            $credit = $this->credit_requested->multiply($this->exchange_rate);

            LedgerEntry::post(
                Str::limit(__('Note de crédit :number — :supplier', ['number' => $this->credit_note_number, 'supplier' => $this->supplier->name]), 250),
                [
                    ['account' => LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE), 'debit' => $credit],
                    ['account' => LedgerAccount::forKey(LedgerAccount::SUPPLIER_CREDITS_RECEIVABLE), 'credit' => $credit],
                ],
                $this,
                $receivedAt,
            );
        });
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
     * @param  array{invoice_number: string, invoice_date: string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: array<string, float|int|string>, invoice_total: float|int|string}  $details
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
            }

            $invoice->postReceptionEntry($reception, $lines);

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
     * @param  array{invoice_number: string, invoice_date: string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: array<string, float|int|string>, invoice_total: float|int|string}  $details
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

            $invoice->postExpenseEntry(LedgerAccount::forKey(LedgerAccount::COST_OF_SERVICES_SOLD));

            return $invoice;
        });
    }

    /**
     * Enregistre une facture rattachée à un fournisseur seulement, sans réception ni commande: un montant de
     * marchandises ou de services, les frais et taxes, comparés au montant de la facture. Aucun effet sur
     * l'inventaire ni sur les commandes.
     *
     * @param  array{invoice_number: string, invoice_date: string, description?: string|null, ledger_account_id: int|string, merchandise_total: float|int|string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: array<string, float|int|string>, invoice_total: float|int|string}  $details
     */
    public static function recordStandalone(Supplier $supplier, array $details, int|string|null $createdBy = null): SupplierInvoice
    {
        $account = LedgerAccount::query()->find($details['ledger_account_id']);

        if ($account === null) {
            throw new DomainException(__('Choisissez le compte de charge de la facture.'));
        }

        return DB::transaction(function () use ($supplier, $details, $createdBy, $account): SupplierInvoice {
            $invoice = self::persist($supplier, ['ledger_account_id' => $account->id], collect(), $details, [], $createdBy);
            $invoice->postExpenseEntry($account);

            return $invoice;
        });
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

            $invoice = self::persist($carrier, [], collect(), [...$details, 'freight_fee' => 0, 'customs_fee' => 0, 'taxes' => $taxAmounts], [], $createdBy);

            if (! $invoice->invoice_total->minus($invoice->taxes)->isPositive()) {
                throw new DomainException(__('Le montant de la facture doit dépasser ses taxes.'));
            }

            CustomerOrderDelivery::query()->whereKey($shipmentIds)->update(['supplier_invoice_id' => $invoice->id]);

            $invoice->postExpenseEntry(
                LedgerAccount::forKey(LedgerAccount::SHIPPING_EXPENSE),
                __('Facture :number — :carrier (:count envois)', ['number' => $invoice->invoice_number, 'carrier' => $carrier->name, 'count' => count($shipmentIds)]),
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
     * @param  array{invoice_number: string, invoice_date: string, description?: string|null, merchandise_total?: float|int|string, freight_fee?: float|int|string, customs_fee?: float|int|string, taxes?: array<string, float|int|string>, invoice_total: float|int|string}  $details
     *                                                                                                                                                                                                                                                                                   Les montants de $details et de $unitCosts sont en dollars, tels que saisis.
     * @param  array<int, float|int|string>  $unitCosts
     */
    private static function persist(Supplier $supplier, array $target, \Illuminate\Support\Collection $lines, array $details, array $unitCosts, int|string|null $createdBy): SupplierInvoice
    {
        $date = Carbon::parse($details['invoice_date']);
        $taxes = collect($details['taxes'] ?? [])
            ->map(fn (float|int|string $amount): Money => Money::parse($amount))
            ->filter(fn (Money $amount): bool => $amount->isPositive());
        $amounts = [
            'freight_fee' => Money::parse($details['freight_fee'] ?? 0),
            'customs_fee' => Money::parse($details['customs_fee'] ?? 0),
            'taxes' => Money::sum($taxes->values()->all()),
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
            'exchange_rate' => $supplier->conversionRate(),
            'created_by' => $createdBy,
            ...$totals,
        ]);

        foreach ($taxes as $taxName => $amount) {
            $invoice->taxLines()->create(['tax_name' => $taxName, 'amount' => $amount]);
        }

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

    /**
     * Écriture de la facture d'une réception (ADR 0016), en CAD au taux de la facture. Le coût réel des marchandises
     * reçues, avec le transport et la douane, est réparti sur les unités au prorata de leur coût : l'écart avec la
     * valeur passée à la réception va au Stock pour les unités en main et au coût des marchandises vendues pour les
     * unités sorties (ou sans unité, comme une pièce). Achats non facturés est soldé de la valeur de la réception.
     *
     * @param  Collection<int, ReceptionLine>  $lines
     */
    private function postReceptionEntry(Reception $reception, Collection $lines): void
    {
        $payable = $this->invoice_total->multiply($this->exchange_rate);
        $taxes = $this->taxLines()->get()->map(fn (SupplierInvoiceTax $tax): array => [LedgerAccount::forTaxReceivable($tax->tax_name), $tax->amount->multiply($this->exchange_rate)]);
        $credit = $this->credit_requested->multiply($this->exchange_rate);
        $variance = $this->variance->multiply($this->exchange_rate);
        $landed = $payable->minus(Money::sum($taxes->map(fn (array $tax): Money => $tax[1])->all()))->minus($credit)->minus($variance);

        $shares = $landed->allocate($lines->mapWithKeys(fn (ReceptionLine $line): array => [
            $line->id => $this->lines->firstWhere('reception_line_id', $line->id)->unit_cost->cents() * $line->quantity_net,
        ])->all());
        $units = InventoryUnit::query()->whereIn('reception_line_id', $lines->modelKeys())->orderBy('id')->get()->groupBy('reception_line_id');

        $received = Money::zero();
        $inStock = Money::zero();
        $sold = Money::zero();

        foreach ($lines as $line) {
            $lineUnits = $units->get($line->id, new Collection);

            if ($lineUnits->isEmpty()) {
                $sold = $sold->plus($shares[$line->id]);

                continue;
            }

            $costs = $shares[$line->id]->allocate($lineUnits->mapWithKeys(fn (InventoryUnit $unit): array => [$unit->id => 1])->all());

            foreach ($lineUnits as $unit) {
                $change = $costs[$unit->id]->minus($unit->cost);
                $received = $received->plus($unit->cost);

                if ($unit->delivered_at === null) {
                    $inStock = $inStock->plus($change);
                } else {
                    $sold = $sold->plus($change);
                }
            }

            foreach ($lineUnits->groupBy(fn (InventoryUnit $unit): int => $costs[$unit->id]->cents()) as $cents => $sameCost) {
                InventoryUnit::query()->whereKey($sameCost->modelKeys())->update(['cost' => $cents]);
            }
        }

        LedgerEntry::post(
            Str::limit(__('Facture :number — :supplier', ['number' => $this->invoice_number, 'supplier' => $reception->supplier->name]), 250),
            LedgerEntry::signedLines([
                [LedgerAccount::forKey(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED), $received],
                [LedgerAccount::forKey(LedgerAccount::INVENTORY), $inStock],
                [LedgerAccount::forKey(LedgerAccount::COST_OF_GOODS_SOLD), $sold],
                [LedgerAccount::forKey(LedgerAccount::SUPPLIER_CREDITS_RECEIVABLE), $credit],
                [LedgerAccount::forKey(LedgerAccount::INVOICE_VARIANCE), $variance],
                ...$taxes->all(),
                [LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE), $payable->negate()],
            ]),
            $this,
            $this->invoice_date,
            $this->created_by,
            $reception->store_id,
        );
    }

    /**
     * Écriture d'une facture sans inventaire (ADR 0016), en CAD au taux de la facture : le montant avant taxes au
     * compte donné (charge, coût des services), chaque taxe à son compte « à recevoir », le total aux fournisseurs.
     */
    private function postExpenseEntry(LedgerAccount $account, ?string $description = null): void
    {
        $payable = $this->invoice_total->multiply($this->exchange_rate);

        if ($payable->isZero()) {
            return;
        }

        $taxes = $this->taxLines()->get()->map(fn (SupplierInvoiceTax $tax): array => [LedgerAccount::forTaxReceivable($tax->tax_name), $tax->amount->multiply($this->exchange_rate)]);

        LedgerEntry::post(
            Str::limit($description ?? __('Facture :number — :supplier', ['number' => $this->invoice_number, 'supplier' => $this->supplier->name]), 250),
            LedgerEntry::signedLines([
                [$account, $payable->minus(Money::sum($taxes->map(fn (array $tax): Money => $tax[1])->all()))],
                ...$taxes->all(),
                [LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE), $payable->negate()],
            ]),
            $this,
            $this->invoice_date,
            $this->created_by,
        );
    }
}
