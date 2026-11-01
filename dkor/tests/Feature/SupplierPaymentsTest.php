<?php

use App\Actions\CancelSupplierPayment;
use App\Actions\RecordSupplierPayment;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierType;
use App\Livewire\Accounting\Payables;
use App\Models\Currency;
use App\Models\LedgerAccount;
use App\Models\LedgerEntryLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('accounting')->create());
});

function payablesBalance(string $key): int
{
    return LedgerEntryLine::where('ledger_account_id', LedgerAccount::forKey($key)->id)->get()
        ->sum(fn (LedgerEntryLine $line): int => $line->debit->cents() - $line->credit->cents());
}

function invoiceFor(Supplier $supplier, string $number, string $total, ?string $date = null): SupplierInvoice
{
    return SupplierInvoice::recordStandalone($supplier, [
        'invoice_number' => $number,
        'invoice_date' => $date ?? today()->toDateString(),
        'merchandise_total' => $total,
        'invoice_total' => $total,
        'ledger_account_id' => LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES)->id,
    ]);
}

/**
 * @param  array<int, array{amount: Money, discount?: bool}>  $allocations
 */
function pay(Supplier $supplier, array $allocations, ?Money $amountCad = null, ?string $paidOn = null): SupplierPayment
{
    return app(RecordSupplierPayment::class)->handle(
        auth()->user(),
        $supplier,
        LedgerAccount::forKey(LedgerAccount::CASH_CAD),
        Carbon::parse($paidOn ?? today()->toDateString()),
        SupplierPaymentMethod::Cheque,
        '1001',
        $allocations,
        $amountCad ?? Money::zero(),
    );
}

it('liste les factures dues par fournisseur et signale les escomptes qui arrivent à échéance', function () {
    $supplier = Supplier::factory()->create(['name' => 'Meubles Nord', 'type' => SupplierType::Product, 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 5]);
    $due = invoiceFor($supplier, 'F-DUE', '100');
    $paid = invoiceFor($supplier, 'F-PAID', '50');
    pay($supplier, [$paid->id => ['amount' => Money::parse(49), 'discount' => true]]);

    $this->get(route('accounting.payables'))->assertOk();

    Livewire::test(Payables::class)
        ->assertSee('Meubles Nord')
        ->assertViewHas('invoicesBySupplier', fn ($groups): bool => $groups->flatten()->pluck('invoice_number')->all() === ['F-DUE'])
        ->assertSee(__(':amount d\'ici le :date', ['amount' => Money::parse(2)->format(), 'date' => $due->discount_due_date->format('Y-m-d')]))
        ->assertViewHas('totalCad', fn (Money $total): bool => $total->cents() === 10000 && $total->cents() === -payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE));
});

it('répartit un paiement sur plusieurs factures et passe son écriture', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $first = invoiceFor($supplier, 'F-1', '100');
    $second = invoiceFor($supplier, 'F-2', '60');

    Livewire::test(Payables::class)
        ->call('openPayment', $supplier->id)
        ->call('payInFull', $first->id)
        ->assertSet("amounts.{$first->id}", '100.00')
        ->set("amounts.{$second->id}", '20')
        ->set('reference', 'CHQ-55')
        ->call('savePayment')
        ->assertHasNoErrors();

    $payment = SupplierPayment::sole();

    expect($payment)->amount->toBeMoney(12000)->amount_cad->toBeMoney(12000)->reference->toBe('CHQ-55')
        ->and($first->fresh()->isPaid())->toBeTrue()
        ->and($second->fresh()->balanceDue())->toBeMoney(4000)
        ->and(payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-4000)
        ->and(payablesBalance(LedgerAccount::CASH_CAD))->toBe(-12000);

    $this->get(route('accounting.invoices.show', $first))->assertSee('Payée')->assertSee('CHQ-55');
});

it('refuse un montant qui dépasse le solde dû ou une facture d\'un autre fournisseur', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $invoice = invoiceFor($supplier, 'F-1', '100');
    $other = invoiceFor(Supplier::factory()->create(), 'F-X', '10');

    Livewire::test(Payables::class)
        ->call('openPayment', $supplier->id)
        ->set("amounts.{$invoice->id}", '100.01')
        ->call('savePayment')
        ->assertHasErrors('amounts');

    expect(fn () => pay($supplier, [$other->id => ['amount' => Money::parse(10)]]))->toThrow(DomainException::class)
        ->and(fn () => pay($supplier, [$invoice->id => ['amount' => Money::zero()]]))->toThrow(DomainException::class)
        ->and(SupplierPayment::count())->toBe(0);
});

it('applique l\'escompte de paiement rapide avant son échéance seulement, au paiement qui solde la facture', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 10]);
    $invoice = invoiceFor($supplier, 'F-1', '100', '2026-10-01');

    expect(fn () => pay($supplier, [$invoice->id => ['amount' => Money::parse(98), 'discount' => true]], paidOn: '2026-10-12'))->toThrow(DomainException::class, 'ne s\'applique plus')
        ->and(fn () => pay($supplier, [$invoice->id => ['amount' => Money::parse(50), 'discount' => true]], paidOn: '2026-10-05'))->toThrow(DomainException::class, 'qui la solde');

    pay($supplier, [$invoice->id => ['amount' => Money::parse(98), 'discount' => true]], paidOn: '2026-10-11');

    expect($invoice->fresh()->isPaid())->toBeTrue()
        ->and(payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(0)
        ->and(payablesBalance(LedgerAccount::PURCHASE_DISCOUNTS))->toBe(-200)
        ->and(payablesBalance(LedgerAccount::CASH_CAD))->toBe(-9800);
});

it('propose le montant net sans les manquants pour une facture avec une demande de crédit', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $invoice = invoiceFor($supplier, 'F-1', '100');
    $invoice->update(['credit_requested' => Money::parse(30)]);

    expect($invoice->fresh()->balanceDue())->toBeMoney(7000);
});

it('porte l\'écart de change d\'une facture en USD au gain ou perte de change et solde au cent près', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'currency_id' => Currency::factory()->create(['code' => 'USD', 'rate' => 1.35])->id]);
    $invoice = invoiceFor($supplier, 'F-USD', '10.01');

    expect(payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-1351);

    pay($supplier, [$invoice->id => ['amount' => Money::parse(5)]], Money::parse('6.60'));
    pay($supplier, [$invoice->id => ['amount' => Money::parse('5.01')]], Money::parse('6.80'));

    expect($invoice->fresh()->isPaid())->toBeTrue()
        ->and(payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(0)
        ->and(payablesBalance(LedgerAccount::CASH_CAD))->toBe(-1340)
        ->and(payablesBalance(LedgerAccount::EXCHANGE_GAIN_LOSS))->toBe(-11);

    expect(fn () => pay($supplier, [invoiceFor($supplier, 'F-USD-2', '10')->id => ['amount' => Money::parse(10)]]))->toThrow(DomainException::class, 'dollars canadiens');
});

it('annule un paiement : les factures redeviennent dues et l\'écriture est renversée', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $invoice = invoiceFor($supplier, 'F-1', '100');
    $payment = pay($supplier, [$invoice->id => ['amount' => Money::parse(100)]]);

    Livewire::test(Payables::class)
        ->call('openCancel', $payment->id)
        ->call('cancelPayment')
        ->assertHasErrors('cancellationReason')
        ->set('cancellationReason', 'Chèque retourné')
        ->call('cancelPayment')
        ->assertHasNoErrors();

    expect($payment->fresh())->isCancelled()->toBeTrue()->cancellation_reason->toBe('Chèque retourné')
        ->and($invoice->fresh()->balanceDue())->toBeMoney(10000)
        ->and(payablesBalance(LedgerAccount::ACCOUNTS_PAYABLE))->toBe(-10000)
        ->and(payablesBalance(LedgerAccount::CASH_CAD))->toBe(0)
        ->and(fn () => app(CancelSupplierPayment::class)->handle(auth()->user(), $payment->fresh(), 'Encore'))->toThrow(DomainException::class);
});

it('réserve les paiements à supplier_payments.create', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);
    $invoice = invoiceFor($supplier, 'F-1', '100');
    $viewer = User::factory()->withRole('salesman')->create();
    $viewer->givePermissionTo('supplier_payments.view');
    $this->actingAs($viewer);

    Livewire::test(Payables::class)->assertSee('F-1')->assertDontSee('Payer')->call('openPayment', $supplier->id)->assertForbidden();

    expect(fn () => pay($supplier, [$invoice->id => ['amount' => Money::parse(100)]]))->toThrow(AuthorizationException::class);
});
