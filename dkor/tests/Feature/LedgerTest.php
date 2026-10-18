<?php

use App\Enums\LedgerAccountType;
use App\Livewire\Accounting\Ledger;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\ReferenceDataSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('accounting')->create());
});

it('crée les comptes système sans toucher à ceux que le comptable a modifiés', function () {
    expect(LedgerAccount::orderBy('code')->pluck('system_key', 'code')->all())->toBe([
        '1300' => LedgerAccount::INVENTORY,
        '3900' => LedgerAccount::OPENING_BALANCE,
        '5110' => LedgerAccount::INVENTORY_SHRINKAGE,
        '5120' => LedgerAccount::INVENTORY_COUNT_VARIANCE,
        '5400' => LedgerAccount::STORE_SUPPLIES,
    ]);

    LedgerAccount::forKey(LedgerAccount::INVENTORY)->update(['code' => '1350', 'name' => 'Stocks']);

    $this->seed(ReferenceDataSeeder::class);

    expect(LedgerAccount::count())->toBe(5)
        ->and(LedgerAccount::forKey(LedgerAccount::INVENTORY))
        ->code->toBe('1350')
        ->name->toBe('Stocks')
        ->type->toBe(LedgerAccountType::Asset);
});

it('passe une écriture équilibrée liée à sa référence', function () {
    $stock = LedgerAccount::forKey(LedgerAccount::INVENTORY);
    $loss = LedgerAccount::forKey(LedgerAccount::INVENTORY_SHRINKAGE);

    $entry = LedgerEntry::post('Perte', [
        ['account' => $loss, 'debit' => Money::parse('12.34')],
        ['account' => $stock, 'credit' => Money::parse('12.34')],
    ], $stock);

    expect($entry->fresh())
        ->description->toBe('Perte')
        ->posted_on->toDateString()->toBe(today()->toDateString())
        ->user_id->toBe(auth()->id())
        ->reference->is($stock)->toBeTrue()
        ->and($entry->total())->toBeMoney(1234)
        ->and($entry->lines)->toHaveCount(2);
});

it('refuse une écriture déséquilibrée, à une ligne ou avec une ligne invalide', function (array $amounts) {
    $stock = LedgerAccount::forKey(LedgerAccount::INVENTORY);
    $loss = LedgerAccount::forKey(LedgerAccount::INVENTORY_SHRINKAGE);

    $lines = array_map(fn (array $line, int $i): array => ['account' => $i === 0 ? $loss : $stock, ...$line], $amounts, array_keys($amounts));

    expect(fn () => LedgerEntry::post('Invalide', $lines))->toThrow(DomainException::class)
        ->and(LedgerEntry::count())->toBe(0);
})->with([
    'déséquilibrée' => [[['debit' => Money::of(100)], ['credit' => Money::of(99)]]],
    'une seule ligne' => [[['debit' => Money::of(100)]]],
    'débit et crédit sur une ligne' => [[['debit' => Money::of(100), 'credit' => Money::of(100)], ['credit' => Money::of(0)]]],
    'montant négatif' => [[['debit' => Money::of(-100)], ['credit' => Money::of(-100)]]],
    'ligne à zéro' => [[['debit' => Money::of(0)], ['credit' => Money::of(0)]]],
]);

it('ne laisse ni modifier ni supprimer une écriture ou ses lignes', function () {
    $entry = LedgerEntry::post('Perte', [
        ['account' => LedgerAccount::forKey(LedgerAccount::INVENTORY_SHRINKAGE), 'debit' => Money::of(100)],
        ['account' => LedgerAccount::forKey(LedgerAccount::INVENTORY), 'credit' => Money::of(100)],
    ]);

    expect(fn () => $entry->update(['description' => 'Autre']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->update(['debit' => Money::of(1)]))->toThrow(LogicException::class)
        ->and(fn () => $entry->lines->first()->delete())->toThrow(LogicException::class);
});

it('affiche les écritures, filtre par compte et modifie le code d\'un compte', function () {
    $stock = LedgerAccount::forKey(LedgerAccount::INVENTORY);
    $loss = LedgerAccount::forKey(LedgerAccount::INVENTORY_SHRINKAGE);
    $supplies = LedgerAccount::forKey(LedgerAccount::STORE_SUPPLIES);

    LedgerEntry::post('Bris en entrepôt', [['account' => $loss, 'debit' => Money::of(500)], ['account' => $stock, 'credit' => Money::of(500)]]);
    LedgerEntry::post('Café du personnel', [['account' => $supplies, 'debit' => Money::of(200)], ['account' => $stock, 'credit' => Money::of(200)]]);

    $this->get(route('accounting.ledger'))->assertOk()->assertSee('Bris en entrepôt')->assertSee('Café du personnel');

    Livewire::test(Ledger::class)
        ->set('accountFilter', (string) $supplies->id)
        ->assertSee('Café du personnel')
        ->assertDontSee('Bris en entrepôt')
        ->call('editAccount', $stock->id)
        ->set('code', '5110')
        ->call('saveAccount')
        ->assertHasErrors('code')
        ->set('code', '1350')
        ->set('name', 'Stocks')
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($stock->fresh())->code->toBe('1350')->name->toBe('Stocks')->system_key->toBe(LedgerAccount::INVENTORY);
});

it('réserve le grand livre à ledger.view et ses comptes à ledger.edit', function () {
    $this->actingAs(User::factory()->withRole('salesman')->create())
        ->get(route('accounting.ledger'))
        ->assertForbidden();

    $viewer = User::factory()->withRole('salesman')->create();
    $viewer->givePermissionTo('ledger.view');

    Livewire::actingAs($viewer)->test(Ledger::class)
        ->call('editAccount', LedgerAccount::forKey(LedgerAccount::INVENTORY)->id)
        ->assertForbidden();
});
