<?php

namespace App\Actions;

use App\Enums\SupplierPaymentMethod;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Enregistre un paiement à un fournisseur (#132, ADR 0016), réparti sur ses factures, et son écriture : les comptes
 * fournisseurs sont débités au taux de chaque facture, le compte d'encaisse crédité du montant réellement déboursé en
 * CAD, l'escompte de paiement rapide crédité aux escomptes sur achats et l'écart au gain ou perte de change.
 */
class RecordSupplierPayment
{
    /**
     * @param  array<int, array{amount: Money, discount?: bool}>  $allocations  Par id de facture : montant payé (devise du fournisseur) et escompte appliqué.
     * @param  Money  $amountCad  Montant réellement déboursé en CAD (ignoré pour un fournisseur en CAD).
     *
     * @throws AuthorizationException sans la permission supplier_payments.create.
     * @throws DomainException si une facture n'est pas du fournisseur, si un montant dépasse le solde dû ou si l'escompte
     *                         ne s'applique pas.
     */
    public function handle(User $user, Supplier $supplier, LedgerAccount $cashAccount, CarbonInterface $paidOn, SupplierPaymentMethod $method, ?string $reference, array $allocations, Money $amountCad): SupplierPayment
    {
        Gate::forUser($user)->authorize('supplier_payments.create');

        $allocations = array_filter($allocations, fn (array $allocation): bool => $allocation['amount']->isPositive() || ($allocation['discount'] ?? false));

        if ($allocations === []) {
            throw new DomainException(__('Indiquez le montant payé sur au moins une facture.'));
        }

        return DB::transaction(function () use ($user, $supplier, $cashAccount, $paidOn, $method, $reference, $allocations, $amountCad): SupplierPayment {
            $invoices = SupplierInvoice::query()->whereKey(array_keys($allocations))->lockForUpdate()->with('paymentAllocations.payment')->get()->keyBy('id');
            $rows = [];

            foreach ($allocations as $invoiceId => $allocation) {
                $invoice = $invoices->get($invoiceId);

                if ($invoice === null || $invoice->supplier_id !== $supplier->id) {
                    throw new DomainException(__('Une facture choisie n\'est pas de ce fournisseur.'));
                }

                $rows[] = $this->allocationFor($invoice, $allocation['amount'], $allocation['discount'] ?? false, $paidOn);
            }

            $amount = Money::sum(array_map(fn (array $row): Money => $row['amount'], $rows));
            $amountCad = $supplier->conversionRate() === '1' ? $amount : $amountCad;

            if ($amount->isPositive() && ! $amountCad->isPositive()) {
                throw new DomainException(__('Indiquez le montant déboursé en dollars canadiens.'));
            }

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'ledger_account_id' => $cashAccount->id,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => filled($reference) ? trim($reference) : null,
                'amount' => $amount,
                'amount_cad' => $amountCad,
                'created_by' => $user->id,
            ]);

            foreach ($rows as $row) {
                $payment->allocations()->create([
                    'supplier_invoice_id' => $row['invoice']->id,
                    'amount' => $row['amount'],
                    'discount' => $row['discount'],
                    'payable_cad' => $row['payable_cad'],
                ]);
            }

            $payable = Money::sum(array_map(fn (array $row): Money => $row['payable_cad'], $rows));
            $discounts = Money::sum(array_map(fn (array $row): Money => $row['discount']->multiply($row['invoice']->exchange_rate), $rows));

            LedgerEntry::post(
                Str::limit(__('Paiement :reference — :supplier', ['reference' => $payment->reference ?? $method->label(), 'supplier' => $supplier->name]), 250),
                LedgerEntry::signedLines([
                    [LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE), $payable],
                    [$cashAccount, $amountCad->negate()],
                    [LedgerAccount::forKey(LedgerAccount::PURCHASE_DISCOUNTS), $discounts->negate()],
                    [LedgerAccount::forKey(LedgerAccount::EXCHANGE_GAIN_LOSS), $amountCad->plus($discounts)->minus($payable)],
                ]),
                $payment,
                $paidOn,
                $user->id,
            );

            return $payment;
        });
    }

    /**
     * Part du paiement sur une facture. L'escompte ne s'applique qu'avant son échéance et quand le paiement solde la
     * facture. Le paiement qui solde la facture prend ce qui reste aux comptes fournisseurs en CAD, pour qu'une facture
     * payée en plusieurs fois les solde au cent près.
     *
     * @return array{invoice: SupplierInvoice, amount: Money, discount: Money, payable_cad: Money}
     */
    private function allocationFor(SupplierInvoice $invoice, Money $amount, bool $applyDiscount, CarbonInterface $paidOn): array
    {
        $due = $invoice->balanceDue();
        $discount = Money::zero();

        if ($amount->isNegative()) {
            throw new DomainException(__('Le montant payé ne peut pas être négatif.'));
        }

        if ($applyDiscount) {
            if (! $invoice->discountAvailableOn($paidOn)) {
                throw new DomainException(__('L\'escompte de la facture :number ne s\'applique plus à cette date.', ['number' => $invoice->invoice_number]));
            }

            $discount = $invoice->discount_amount;

            if (! $amount->plus($discount)->equals($due)) {
                throw new DomainException(__('L\'escompte de la facture :number ne s\'applique qu\'au paiement qui la solde (:amount).', ['number' => $invoice->invoice_number, 'amount' => $due->minus($discount)->format()]));
            }
        }

        $settled = $amount->plus($discount);

        if ($settled->greaterThan($due)) {
            throw new DomainException(__('Le montant appliqué à la facture :number dépasse son solde dû (:due).', ['number' => $invoice->invoice_number, 'due' => $due->format()]));
        }

        $payableCad = $settled->equals($due)
            ? $invoice->payableCad()->minus(Money::sum($invoice->activeAllocations()->map(fn ($allocation): Money => $allocation->payable_cad)->all()))
            : $settled->multiply($invoice->exchange_rate);

        return ['invoice' => $invoice, 'amount' => $amount, 'discount' => $discount, 'payable_cad' => $payableCad];
    }
}
