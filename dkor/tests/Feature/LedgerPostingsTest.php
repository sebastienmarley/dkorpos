<?php

use App\Actions\AdjustInventory;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryStatus;
use App\Enums\SupplierType;
use App\Livewire\Accounting\Invoices\Create;
use App\Models\Currency;
use App\Models\InventoryUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Solde d'un compte en cents : débits moins crédits.
 */
function accountBalance(LedgerAccount $account): int
{
    $lines = LedgerEntryLine::where('ledger_account_id', $account->id)->get();

    return $lines->sum(fn (LedgerEntryLine $line): int => $line->debit->cents() - $line->credit->cents());
}

function systemBalance(string $key): int
{
    return accountBalance(LedgerAccount::forKey($key));
}

function usdSupplier(): Supplier
{
    return Supplier::factory()->create(['type' => SupplierType::Product, 'currency_id' => Currency::factory()->create(['code' => 'USD', 'rate' => 1.35])->id]);
}

/**
 * Commande envoyée d'un produit, dont on reçoit une partie et dont une autre est annoncée manquante.
 */
function receiveWithShort(Supplier $supplier, int $received, int $short, float $cost): Reception
{
    $order = SupplierOrder::factory()->create(['supplier_id' => $supplier->id]);
    $line = SupplierOrderLine::factory()
        ->forProduct(Product::factory()->create(['supplier_id' => $supplier->id]))
        ->create(['supplier_order_id' => $order->id, 'quantity' => $received + $short, 'unit_cost' => Money::parse($cost)]);
    $order->markPending();
    $order->fresh()->send();

    $reception = Reception::start($supplier, [$line->id => ['quantity' => $received, 'short' => $short]]);
    $reception->complete();

    return $reception->fresh();
}

it('passe la réception au Stock et aux achats non facturés, en CAD au taux de la devise du fournisseur', function () {
    $product = Product::factory()->create(['supplier_id' => usdSupplier()->id]);

    $reception = receiveProduct($product, 4, 10.0)->reception;
    $entry = LedgerEntry::sole();

    expect($reception->exchange_rate)->toBe('1.350000')
        ->and(InventoryUnit::pluck('cost')->map->cents()->all())->toBe([1350, 1350, 1350, 1350])
        ->and($entry->reference->is($reception))->toBeTrue()
        ->and($entry->store_id)->toBe($reception->store_id)
        ->and(systemBalance(LedgerAccount::INVENTORY))->toBe(5400)
        ->and(systemBalance(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED))->toBe(-5400);
});

it('renverse l\'écriture de la réception pour la quantité renversée', function () {
    $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $receptionLine = receiveProduct($product, 4, 5.0);

    $receptionLine->orderLine->order->reverseReceipt($receptionLine, 1, 'Erreur de saisie');

    expect(LedgerEntry::count())->toBe(2)
        ->and(systemBalance(LedgerAccount::INVENTORY))->toBe(1500)
        ->and(systemBalance(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED))->toBe(-1500);
});

it('solde les achats non facturés et répartit le coût réel, le transport et la douane sur les unités', function () {
    $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $receptionLine = receiveProduct($product, 3, 10.0);
    app(AdjustInventory::class)->handle(auth()->user(), $product, $receptionLine->reception->store_id, InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 1, 'Bris');

    $invoice = SupplierInvoice::record($receptionLine->reception, [
        'invoice_number' => 'F-1',
        'invoice_date' => '2026-10-15',
        'freight_fee' => '3',
        'customs_fee' => '1.50',
        'taxes' => ['TPS' => '2.03', 'TVQ' => '4.04'],
        'invoice_total' => '46.60',
    ], [$receptionLine->id => '12']);

    $entry = LedgerEntry::where('reference_type', $invoice->getMorphClass())->sole();

    expect(InventoryUnit::pluck('cost')->map->cents()->all())->toBe([1350, 1350, 1350])
        ->and($entry->posted_on->toDateString())->toBe('2026-10-15')
        ->and($entry->total())->toBeMoney(4660)
        ->and(systemBalance(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED))->toBe(0)
        ->and(systemBalance(LedgerAccount::INVENTORY))->toBe(InventoryUnit::inHand()->get()->sum(fn (InventoryUnit $unit): int => $unit->cost->cents()))
        ->and(systemBalance(LedgerAccount::INVENTORY))->toBe(2700)
        ->and(systemBalance(LedgerAccount::COST_OF_GOODS_SOLD))->toBe(350)
        ->and(systemBalance(LedgerAccount::INVOICE_VARIANCE))->toBe(3)
        ->and(accountBalance(LedgerAccount::forTaxReceivable('TPS')))->toBe(203)
        ->and(accountBalance(LedgerAccount::forTaxReceivable('TVQ')))->toBe(404)
        ->and(systemBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-4660);
});

it('convertit la facture au taux figé et solde les manquants à la note de crédit', function () {
    $reception = receiveWithShort(usdSupplier(), received: 2, short: 1, cost: 10.0);

    $invoice = SupplierInvoice::record($reception, ['invoice_number' => 'F-USD', 'invoice_date' => '2026-10-15', 'invoice_total' => '30']);

    expect($invoice->exchange_rate)->toBe('1.350000')
        ->and($invoice->credit_requested)->toBeMoney(1000)
        ->and(systemBalance(LedgerAccount::GOODS_RECEIVED_NOT_INVOICED))->toBe(0)
        ->and(systemBalance(LedgerAccount::SUPPLIER_CREDITS_RECEIVABLE))->toBe(1350)
        ->and(systemBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-4050);

    Currency::where('code', 'USD')->update(['rate' => 1.5]);
    $invoice->fresh()->recordCreditNote('NC-1', now());

    expect(systemBalance(LedgerAccount::SUPPLIER_CREDITS_RECEIVABLE))->toBe(0)
        ->and(systemBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-2700);
});

it('passe une facture de services au coût des services vendus', function () {
    $order = SupplierOrder::factory()->service()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Service])->id]);
    SupplierOrderLine::factory()->create(['supplier_order_id' => $order->id, 'quantity' => 2, 'unit_cost' => Money::parse(100)]);
    $order->send();
    $order->fresh()->complete();

    SupplierInvoice::recordForOrder($order->fresh(), ['invoice_number' => 'S-1', 'invoice_date' => '2026-10-15', 'taxes' => ['TPS' => '10'], 'invoice_total' => '210']);

    expect(systemBalance(LedgerAccount::COST_OF_SERVICES_SOLD))->toBe(20000)
        ->and(accountBalance(LedgerAccount::forTaxReceivable('TPS')))->toBe(1000)
        ->and(systemBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-21000);
});

it('passe une facture libre au compte de charge choisi, obligatoire', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Service, 'is_active' => true]);
    $supplies = LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES);
    $details = ['invoice_number' => 'L-1', 'invoice_date' => '2026-10-15', 'merchandise_total' => '100', 'taxes' => ['TPS' => '5'], 'invoice_total' => '105'];

    expect(fn () => SupplierInvoice::recordStandalone($supplier, [...$details, 'ledger_account_id' => 0]))->toThrow(DomainException::class);

    Livewire::test(Create::class)
        ->set('supplierId', (string) $supplier->id)
        ->set('invoiceNumber', 'L-1')->set('merchandiseTotal', '100')->set('taxAmounts.TPS', '5')->set('invoiceTotal', '105')
        ->set('ledgerAccountId', (string) LedgerAccount::forKey(LedgerAccount::ACCOUNTS_PAYABLE)->id)
        ->call('save')
        ->assertHasErrors('ledgerAccountId')
        ->set('ledgerAccountId', (string) $supplies->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(SupplierInvoice::sole()->ledger_account_id)->toBe($supplies->id)
        ->and(accountBalance($supplies))->toBe(10000)
        ->and(accountBalance(LedgerAccount::forTaxReceivable('TPS')))->toBe(500)
        ->and(systemBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-10500);
});
