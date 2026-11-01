<?php

namespace App\Support;

use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contrôles d'exactitude du grand livre (ADR 0016, I11 à I15) : le solde de chaque compte de contrôle comparé à la
 * valeur que donnent les documents. Les soldes sont en débits moins crédits.
 */
class LedgerChecks
{
    /**
     * @return list<array{invariant: string, label: string, ledger: Money, expected: Money}>
     */
    public function run(): array
    {
        return [
            ['invariant' => 'I11', 'label' => __('Stock = valeur FIFO des unités en main'), 'ledger' => $this->balance(LedgerAccount::INVENTORY), 'expected' => $this->inventoryValue()],
            ['invariant' => 'I12', 'label' => __('Achats non facturés = réceptions sans facture'), 'ledger' => $this->balance(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED)->negate(), 'expected' => $this->uninvoicedReceptions()],
            ['invariant' => 'I13', 'label' => __('Dépôts moins comptes clients = payé moins vendu, plus crédits des clients'), 'ledger' => $this->balance(LedgerAccount::CUSTOMER_DEPOSITS)->negate()->minus($this->balance(LedgerAccount::ACCOUNTS_RECEIVABLE)), 'expected' => $this->customerDeposits()],
            ['invariant' => 'I15', 'label' => __('Comptes fournisseurs = soldes dus des factures au grand livre'), 'ledger' => $this->balance(LedgerAccount::ACCOUNTS_PAYABLE)->negate(), 'expected' => $this->payables()],
        ];
    }

    private function balance(string $key): Money
    {
        return Money::of((int) LedgerEntryLine::query()
            ->where('ledger_account_id', LedgerAccount::forKey($key)->id)
            ->selectRaw('coalesce(sum(debit) - sum(credit), 0) as balance')
            ->value('balance'));
    }

    private function inventoryValue(): Money
    {
        return Money::sum(InventoryUnit::query()->inHand()->get()->map(fn (InventoryUnit $unit): Money => $unit->cost)->all());
    }

    /**
     * Valeur nette des unités reçues (moins les renversements) des réceptions terminées qui n'ont pas de facture.
     */
    private function uninvoicedReceptions(): Money
    {
        return Money::sum(InventoryMovement::query()
            ->where('reference_type', (new ReceptionLine)->getMorphClass())
            ->whereIn('reference_id', ReceptionLine::query()->select('id')->whereIn('reception_id', Reception::query()->select('id')->whereDoesntHave('invoice')))
            ->whereNotNull('value')
            ->get()
            ->map(fn (InventoryMovement $movement): Money => $movement->to_status?->isValued() ? $movement->value : $movement->value->negate())
            ->all());
    }

    private function customerDeposits(): Money
    {
        $orders = CustomerOrder::query()->get();

        return Money::sum($orders->map(fn (CustomerOrder $order): Money => $order->amount_paid->minus($order->recognizedSales()))->all())
            ->plus(Money::sum(customer::query()->get()->map(fn (customer $customer): Money => $customer->credit_balance)->all()));
    }

    /**
     * Total en CAD des factures au grand livre, moins les notes de crédit reçues et les paiements actifs.
     */
    private function payables(): Money
    {
        $invoices = SupplierInvoice::query()
            ->whereIn('id', LedgerEntry::query()->where('reference_type', (new SupplierInvoice)->getMorphClass())->select('reference_id'))
            ->get();

        $paid = SupplierPaymentAllocation::query()
            ->whereHas('payment', fn (Builder $payment) => $payment->whereNull('cancelled_at'))
            ->whereIn('supplier_invoice_id', $invoices->modelKeys())
            ->get();

        return Money::sum($invoices->map(fn (SupplierInvoice $invoice): Money => $invoice->credit_received_at !== null ? $invoice->payableCad() : $invoice->invoice_total->multiply($invoice->exchange_rate))->all())
            ->minus(Money::sum($paid->map(fn (SupplierPaymentAllocation $allocation): Money => $allocation->payable_cad)->all()));
    }
}
