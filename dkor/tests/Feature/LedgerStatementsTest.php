<?php

use App\Actions\RecordSupplierPayment;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierType;
use App\Livewire\Accounting\Statements;
use App\Models\CustomerOrder;
use App\Models\CustomerPaymentMethod;
use App\Models\LedgerAccount;
use App\Models\LedgerClosing;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

/**
 * Écriture simple de fournitures payées comptant, à la date et au magasin donnés.
 */
function suppliesEntry(string $date, int $cents, ?int $storeId = null): LedgerEntry
{
    return LedgerEntry::post('Fournitures', [
        ['account' => LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES), 'debit' => Money::of($cents)],
        ['account' => LedgerAccount::forKey(LedgerAccount::CASH_CAD), 'credit' => Money::of($cents)],
    ], postedOn: Carbon::parse($date), storeId: $storeId);
}

it('reporte au premier jour ouvert une écriture datée dans une période clôturée, jusqu\'à sa réouverture', function () {
    $this->travelTo('2026-11-15');

    Livewire::test(Statements::class)
        ->set('tab', 'closing')
        ->set('closeThrough', '2026-11-15')
        ->call('closePeriod')
        ->assertHasErrors('closeThrough')
        ->set('closeThrough', '2026-10-31')
        ->call('closePeriod')
        ->assertHasNoErrors()
        ->set('closeThrough', '2026-09-30')
        ->call('closePeriod')
        ->assertHasErrors('closeThrough');

    $carried = suppliesEntry('2026-10-20', 1000);

    expect(LedgerClosing::closedThrough()->toDateString())->toBe('2026-10-31')
        ->and($carried->posted_on->toDateString())->toBe('2026-11-01')
        ->and($carried->description)->toBe('Fournitures (document du 2026-10-20)')
        ->and(suppliesEntry('2026-11-02', 1000)->posted_on->toDateString())->toBe('2026-11-02');

    Livewire::test(Statements::class)
        ->set('reopenFrom', '2026-10-01')
        ->call('reopenPeriod')
        ->assertHasNoErrors();

    expect(LedgerClosing::closedThrough()->toDateString())->toBe('2026-09-30')
        ->and(suppliesEntry('2026-10-20', 1000)->posted_on->toDateString())->toBe('2026-10-20');
});

it('réserve la clôture à ledger.close', function () {
    $viewer = User::factory()->withRole('salesman')->create();
    $viewer->givePermissionTo('ledger.view');
    $this->actingAs($viewer);

    Livewire::test(Statements::class)
        ->set('tab', 'closing')
        ->assertDontSee(__('Clôturer jusqu\'au'))
        ->call('closePeriod')
        ->assertForbidden();

    expect(LedgerClosing::count())->toBe(0);
});

it('donne une balance équilibrée par compte et le grand livre d\'un compte avec son solde courant, par magasin', function () {
    $storeId = testStoreId();
    suppliesEntry('2026-09-15', 500, $storeId);
    suppliesEntry('2026-10-05', 1000, $storeId);
    suppliesEntry('2026-10-10', 250, $storeId);
    suppliesEntry('2026-10-12', 7000);

    $supplies = LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES);

    Livewire::test(Statements::class)
        ->set('from', '2026-10-01')
        ->set('to', '2026-10-31')
        ->set('storeFilter', (string) $storeId)
        ->assertViewHas('balance', fn ($balance): bool => $balance->firstWhere('account.id', $supplies->id)['opening']->cents() === 500
            && $balance->firstWhere('account.id', $supplies->id)['debit']->cents() === 1250
            && $balance->firstWhere('account.id', $supplies->id)['closing']->cents() === 1750)
        ->assertViewHas('totals', fn (array $totals): bool => $totals['debit']->equals($totals['credit']) && $totals['debit']->cents() === 1250)
        ->call('showAccount', $supplies->id)
        ->assertSet('tab', 'account')
        ->assertViewHas('accountLedger', fn (array $ledger): bool => $ledger['opening']->cents() === 500
            && $ledger['lines']->map(fn (array $row): int => $row['balance']->cents())->all() === [1500, 1750]
            && $ledger['closing']->cents() === 1750)
        ->call('exportBalance')
        ->assertFileDownloaded('balance_2026-10-01_2026-10-31.csv');
});

it('donne les taxes perçues, payées et le net à remettre de la période', function () {
    $this->travelTo('2026-10-20');
    $order = CustomerOrder::factory()->create();
    $line = stockedLine($order, 1, 1, 100);
    $order->pickUp([$line->id => 1], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $order->fresh()->balance_due]]);

    SupplierInvoice::recordStandalone(Supplier::factory()->create(['type' => SupplierType::Service]), [
        'invoice_number' => 'L-1',
        'invoice_date' => '2026-10-15',
        'merchandise_total' => '20',
        'taxes' => ['TPS' => '1'],
        'invoice_total' => '21',
        'ledger_account_id' => LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES)->id,
    ]);

    Livewire::test(Statements::class)
        ->set('from', '2026-10-01')
        ->set('to', '2026-10-31')
        ->set('tab', 'taxes')
        ->assertViewHas('taxes', fn ($taxes): bool => $taxes['TPS']['collected']->cents() === 500
            && $taxes['TPS']['paid']->cents() === 100
            && $taxes['TPS']['net']->cents() === 400
            && $taxes['TVQ']['net']->cents() === 998);
});

it('vérifie que les comptes de contrôle concordent avec les documents', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $receptionLine = receiveProduct($product, 3, 10.0);
    receiveProduct($product, 1, 12.0);

    $invoice = SupplierInvoice::record($receptionLine->reception, ['invoice_number' => 'F-1', 'invoice_date' => today()->toDateString(), 'freight_fee' => '3', 'invoice_total' => '33']);
    app(RecordSupplierPayment::class)->handle(auth()->user(), $supplier, LedgerAccount::forKey(LedgerAccount::CASH_CAD), today(), SupplierPaymentMethod::Cheque, null, [$invoice->id => ['amount' => Money::parse(20)]], Money::zero());

    $order = CustomerOrder::factory()->create();
    $line = $order->addProduct($product, 2);
    $order->pickUp([$line->id => 2], [['method_id' => CustomerPaymentMethod::cash()->id, 'amount' => $order->fresh()->balance_due]]);
    $order->fresh()->completeServiceLine($order->addService(Service::factory()->internal(Money::parse(80))->create(), null, 1, 'Livraison'));

    $this->artisan('ledger:check')->expectsOutputToContain('concorde')->assertSuccessful();

    suppliesEntry(today()->toDateString(), 100);
    LedgerEntry::post('Écart', [
        ['account' => LedgerAccount::forKey(LedgerAccount::INVENTORY), 'debit' => Money::of(1)],
        ['account' => LedgerAccount::forKey(LedgerAccount::OPENING_BALANCE), 'credit' => Money::of(1)],
    ]);

    $this->artisan('ledger:check')->expectsOutputToContain('✘ I11')->assertFailed();
});
