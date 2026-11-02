<?php

use App\Actions\RecordJournalEntry;
use App\Actions\ReverseJournalEntry;
use App\Enums\LedgerAccountType;
use App\Livewire\Accounting\Journal;
use App\Livewire\Accounting\Ledger;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerClosing;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('accounting')->create());
});

function journalBalance(LedgerAccount|string $account): int
{
    $account = is_string($account) ? LedgerAccount::forKey($account) : $account;

    return LedgerEntryLine::where('ledger_account_id', $account->id)->get()
        ->sum(fn (LedgerEntryLine $line): int => $line->debit->cents() - $line->credit->cents());
}

/**
 * @param  list<array{0: LedgerAccount, 1: int, 2: int}>  $lines  compte, débit et crédit en cents
 */
function journalEntry(string $description, array $lines, string $date = '2026-11-01'): JournalEntry
{
    return app(RecordJournalEntry::class)->handle(
        auth()->user(),
        Carbon::parse($date),
        $description,
        null,
        null,
        array_map(fn (array $line): array => ['account' => $line[0], 'debit' => Money::of($line[1]), 'credit' => Money::of($line[2])], $lines),
    );
}

it('passe un solde d\'ouverture saisi au journal général, équilibré et complet', function () {
    $cash = LedgerAccount::forKey(LedgerAccount::CASH_CAD);
    $opening = LedgerAccount::forKey(LedgerAccount::OPENING_BALANCE);

    Livewire::test(Journal::class)
        ->call('openEntry')
        ->set('entryDate', '2026-11-01')
        ->set('description', 'Solde d\'ouverture')
        ->set('documentNumber', 'Relevé oct.')
        ->set('lines.0.account_id', (string) $cash->id)->set('lines.0.debit', '12500')
        ->set('lines.1.credit', '12500')
        ->call('save')
        ->assertHasErrors('lines')
        ->set('lines.1.account_id', (string) $opening->id)->set('lines.1.credit', '12000')
        ->assertViewHas('totals', fn (array $totals): bool => $totals['debit']->cents() === 1250000 && $totals['credit']->cents() === 1200000)
        ->call('save')
        ->assertHasErrors('lines')
        ->set('lines.1.credit', '12500')
        ->call('addLine')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Solde d\'ouverture');

    $entry = JournalEntry::sole();

    expect($entry->ledgerEntry->description)->toBe('Solde d\'ouverture (Relevé oct.)')
        ->and($entry->ledgerEntry->posted_on->toDateString())->toBe('2026-11-01')
        ->and($entry->ledgerEntry->sourceUrl())->toBe(route('accounting.journal'))
        ->and(journalBalance($cash))->toBe(1250000)
        ->and(journalBalance($opening))->toBe(-1250000);
});

it('solde le compte de compensation au dépôt du processeur, frais compris, sur un compte ajouté', function () {
    Livewire::test(Ledger::class)
        ->call('createAccount')
        ->set('code', '5610')
        ->set('name', 'Frais de cartes')
        ->set('type', LedgerAccountType::Expense->value)
        ->call('saveAccount')
        ->assertHasNoErrors();

    $fees = LedgerAccount::where('code', '5610')->sole();
    $clearing = LedgerAccount::factory()->create(['code' => '1030', 'name' => 'Compensation Visa', 'type' => LedgerAccountType::Asset]);

    journalEntry('Ventes Visa', [[$clearing, 10000, 0], [LedgerAccount::forKey(LedgerAccount::CUSTOMER_DEPOSITS), 0, 10000]]);
    journalEntry('Dépôt Moneris', [[LedgerAccount::forKey(LedgerAccount::CASH_CAD), 9750, 0], [$fees, 250, 0], [$clearing, 0, 10000]]);

    expect($fees)->type->toBe(LedgerAccountType::Expense)->system_key->toBeNull()
        ->and(journalBalance($clearing))->toBe(0)
        ->and(journalBalance($fees))->toBe(250);
});

it('renverse une écriture manuelle une seule fois, au premier jour ouvert si la période est clôturée', function () {
    $this->travelTo('2026-11-15');
    $cash = LedgerAccount::forKey(LedgerAccount::CASH_CAD);
    $entry = journalEntry('Frais bancaires', [[LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES), 1500, 0], [$cash, 0, 1500]], '2026-10-20');
    LedgerClosing::close(Carbon::parse('2026-10-31'));

    Livewire::test(Journal::class)->call('reverse', $entry->id)->assertSee('Renversée le 2026-11-15');

    $reversal = $entry->fresh()->reversal;

    expect($reversal->ledgerEntry->posted_on->toDateString())->toBe('2026-11-15')
        ->and($reversal->description)->toBe('Renversement — Frais bancaires')
        ->and(journalBalance($cash))->toBe(0)
        ->and(fn () => app(ReverseJournalEntry::class)->handle(auth()->user(), $entry, today()))->toThrow(DomainException::class, 'déjà renversée')
        ->and(fn () => app(ReverseJournalEntry::class)->handle(auth()->user(), $reversal, today()))->toThrow(DomainException::class)
        ->and(LedgerEntry::count())->toBe(2);
});

it('réserve les écritures manuelles à ledger.journal', function () {
    $entry = journalEntry('Virement', [[LedgerAccount::forKey(LedgerAccount::CASH_USD), 100, 0], [LedgerAccount::forKey(LedgerAccount::CASH_CAD), 0, 100]]);
    $viewer = User::factory()->withRole('salesman')->create();
    $viewer->givePermissionTo('ledger.view');
    $this->actingAs($viewer);

    Livewire::test(Journal::class)
        ->assertSee('Virement')
        ->assertDontSeeHtml('wire:click="openEntry"')
        ->call('openEntry')->assertForbidden();

    Livewire::test(Journal::class)->call('reverse', $entry->id)->assertForbidden();

    expect(fn () => journalEntry('Autre', [[LedgerAccount::forKey(LedgerAccount::CASH_USD), 100, 0], [LedgerAccount::forKey(LedgerAccount::CASH_CAD), 0, 100]]))->toThrow(AuthorizationException::class);
});
