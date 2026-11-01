<?php

use App\Enums\LedgerAccountType;
use App\Enums\SupplierType;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderTax;
use App\Models\CustomerPaymentMethod;
use App\Models\InventoryUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $this->user = User::factory()->withRole('visiteur')->create();
    $this->user->givePermissionTo(['customer_orders.view', 'customer_orders.edit']);
    $this->actingAs($this->user);

    $this->order = CustomerOrder::factory()->create();
    $this->cash = CustomerPaymentMethod::cash();
});

/**
 * Solde d'un compte en cents : débits moins crédits.
 */
function salesBalance(LedgerAccount|string $account): int
{
    $account = is_string($account) ? LedgerAccount::forKey($account) : $account;

    return LedgerEntryLine::where('ledger_account_id', $account->id)->get()
        ->sum(fn (LedgerEntryLine $line): int => $line->debit->cents() - $line->credit->cents());
}

/**
 * Soldes des taxes à payer, par taxe de la commande.
 *
 * @return array<string, int>
 */
function taxesPayable(CustomerOrder $order): array
{
    return $order->taxLines->mapWithKeys(fn (CustomerOrderTax $tax): array => [$tax->name => salesBalance(LedgerAccount::forTaxPayable($tax->name))])->all();
}

it('passe le paiement aux dépôts de clients, puis la vente, ses taxes et son coût au ramassage', function () {
    $line = stockedLine($this->order, 1, 1, 19.99);
    $unitCost = InventoryUnit::where('product_id', $line->product_id)->sole()->cost->cents();

    $this->order->pickUp([$line->id => 1], [['method_id' => $this->cash->id, 'amount' => $this->order->fresh()->balance_due]]);

    $order = $this->order->fresh();

    expect($order->total)->toBeMoney(2298)
        ->and(salesBalance(LedgerAccount::CASH_CAD))->toBe(2300)
        ->and(salesBalance(LedgerAccount::CASH_ROUNDING))->toBe(-2)
        ->and(salesBalance(LedgerAccount::CUSTOMER_DEPOSITS))->toBe(0)
        ->and(salesBalance(LedgerAccount::ACCOUNTS_RECEIVABLE))->toBe(0)
        ->and(salesBalance(LedgerAccount::MERCHANDISE_SALES))->toBe(-1999)
        ->and(array_sum(taxesPayable($order)))->toBe(-299)
        ->and(salesBalance(LedgerAccount::COST_OF_GOODS_SOLD))->toBe($unitCost)
        ->and(salesBalance(LedgerAccount::INVENTORY))->toBe(-$unitCost)
        ->and(LedgerEntry::where('store_id', $order->store_id)->count())->toBe(2);
});

it('répartit les taxes sur les ramassages partiels pour que leur somme égale celles de la commande', function () {
    $line = stockedLine($this->order, 3, 3, 0.33);
    $card = CustomerPaymentMethod::factory()->create();
    $this->order->pickUp([], [['method_id' => $card->id, 'amount' => $this->order->fresh()->balance_due]]);

    foreach (range(1, 3) as $pickup) {
        $this->order->fresh()->pickUp([$line->id => 1], []);
    }

    $order = $this->order->fresh();

    expect(taxesPayable($order))->toBe($order->taxLines->mapWithKeys(fn (CustomerOrderTax $tax): array => [$tax->name => -$tax->amount->cents()])->all())
        ->and(salesBalance(LedgerAccount::MERCHANDISE_SALES))->toBe(-99)
        ->and(salesBalance(LedgerAccount::CUSTOMER_DEPOSITS))->toBe(0)
        ->and(LedgerEntry::whereMorphedTo('reference', $order)->count())->toBe(3);
});

it('renverse la vente, les taxes et le coût d\'un article retourné et remboursé', function () {
    $line = pickedUpLine($this->order, 1, 100, $this->cash);

    $this->order->returnLine($line, 1, refund: true, refunds: [['method_id' => $this->cash->id, 'amount' => Money::parse(114.98)]]);

    foreach ([LedgerAccount::CASH_CAD, LedgerAccount::CASH_ROUNDING, LedgerAccount::CUSTOMER_DEPOSITS, LedgerAccount::MERCHANDISE_SALES, LedgerAccount::COST_OF_GOODS_SOLD, LedgerAccount::INVENTORY] as $account) {
        expect(salesBalance($account))->toBe(0, $account);
    }

    expect(taxesPayable($this->order->fresh()))->each->toBe(0);
});

it('passe aux comptes clients un service complété non payé, puis au compte du mode de paiement à son paiement', function () {
    $clearing = LedgerAccount::factory()->create(['code' => '1030', 'name' => 'Compensation Visa', 'type' => LedgerAccountType::Asset]);
    $visa = CustomerPaymentMethod::factory()->create(['ledger_account_id' => $clearing->id]);
    $line = $this->order->addService(Service::factory()->internal(Money::parse(80))->create(), null, 1, 'Livraison');

    $this->order->completeServiceLine($line);

    expect(salesBalance(LedgerAccount::SERVICE_SALES))->toBe(-8000)
        ->and(salesBalance(LedgerAccount::ACCOUNTS_RECEIVABLE))->toBe(9198);

    $this->order->fresh()->pickUp([], [['method_id' => $visa->id, 'amount' => Money::parse(91.98)]]);

    expect(salesBalance(LedgerAccount::ACCOUNTS_RECEIVABLE))->toBe(0)
        ->and(salesBalance(LedgerAccount::CUSTOMER_DEPOSITS))->toBe(0)
        ->and(salesBalance($clearing))->toBe(9198)
        ->and(salesBalance(LedgerAccount::CASH_CAD))->toBe(0);
});

it('passe les frais d\'annulation et leurs taxes', function () {
    $this->order->store->update(['cancellation_fee_percent' => 15]);
    $product = Product::factory()->create(['supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id]);
    $line = $this->order->addProduct($product, 2);
    $this->order->updateLine($line, 0, 2, Money::parse(33.33), null);
    $line->fresh()->supplierOrderLine->order->send();

    $this->order->fresh()->cancelLineWithFee($line->fresh());

    expect(salesBalance(LedgerAccount::CANCELLATION_FEES))->toBe(-1000)
        ->and(array_sum(taxesPayable($this->order->fresh())))->toBe(-150)
        ->and(salesBalance(LedgerAccount::ACCOUNTS_RECEIVABLE))->toBe(1150);
});
