<?php

use App\Actions\AdjustInventory;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierType;
use App\Livewire\Inventory\Adjustments;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->withRole('owner')->create();
    $this->actingAs($this->owner);
    $this->product = Product::factory()->create([
        'supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id,
        'cost' => Money::parse(9),
    ]);
});

function adjust(Product $product, InventoryAdjustmentReason $reason, InventoryStatus $status, bool $isEntry, int $quantity, string $note = 'Constaté au plancher'): InventoryAdjustment
{
    return app(AdjustInventory::class)->handle(auth()->user(), $product, testStoreId(), $reason, $status, $isEntry, $quantity, $note);
}

/**
 * Lignes d'une écriture : [code du compte, débit, crédit] en cents.
 *
 * @return list<array{0: string, 1: int, 2: int}>
 */
function entryLines(LedgerEntry $entry): array
{
    return $entry->lines()->with('account')->orderBy('id')->get()
        ->map(fn (LedgerEntryLine $line): array => [$line->account->code, $line->debit->cents(), $line->credit->cents()])
        ->all();
}

it('sort une perte au coût des unités les plus anciennes et passe l\'écriture Pertes / Stock', function () {
    receiveProduct($this->product, 2, 5);
    $this->travel(1)->day();
    receiveProduct($this->product, 2, 7);

    $adjustment = adjust($this->product, InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 3, 'Boîtes écrasées');

    expect($adjustment->movement)
        ->type->toBe(InventoryMovementType::Adjustment)
        ->from_status->toBe(InventoryStatus::InStock)
        ->to_status->toBeNull()
        ->note->toBe('Boîtes écrasées')
        ->value->toBeMoney(1700)
        ->and(entryLines($adjustment->ledgerEntry))->toBe([['5110', 1700, 0], ['1300', 0, 1700]])
        ->and($adjustment->ledgerEntry->user_id)->toBe($this->owner->id)
        ->and(InventoryUnit::inHand()->sole()->cost)->toBeMoney(700);

    expectInventoryIntegrity($this->product);
});

it('entre le stock initial au coût du produit : Stock / Solde d\'ouverture', function () {
    $adjustment = adjust($this->product, InventoryAdjustmentReason::OpeningBalance, InventoryStatus::InStock, true, 4);

    expect($adjustment->movement->value)->toBeMoney(3600)
        ->and(InventoryUnit::inHand()->where('cost', 900)->count())->toBe(4)
        ->and(entryLines($adjustment->ledgerEntry))->toBe([['1300', 3600, 0], ['3900', 0, 3600]])
        ->and($this->product->inventoryStocks()->sole()->quantity_in_stock)->toBe(4);

    expectInventoryIntegrity($this->product);
});

it('corrige un comptage dans les deux sens et sort l\'usage magasin', function () {
    $found = adjust($this->product, InventoryAdjustmentReason::CountCorrection, InventoryStatus::InDemo, true, 2);
    $missing = adjust($this->product, InventoryAdjustmentReason::CountCorrection, InventoryStatus::InDemo, false, 1);
    $used = adjust($this->product, InventoryAdjustmentReason::StoreUse, InventoryStatus::InDemo, false, 1);

    expect(entryLines($found->ledgerEntry))->toBe([['1300', 1800, 0], ['5120', 0, 1800]])
        ->and(entryLines($missing->ledgerEntry))->toBe([['5120', 900, 0], ['1300', 0, 900]])
        ->and(entryLines($used->ledgerEntry))->toBe([['5400', 900, 0], ['1300', 0, 900]])
        ->and($this->product->inventoryStocks()->sole()->quantity_in_demo)->toBe(0);

    expectInventoryIntegrity($this->product);
});

it('refuse un motif, un sens, un état, une note ou une quantité invalides sans rien enregistrer', function (InventoryAdjustmentReason $reason, InventoryStatus $status, bool $isEntry, int $quantity, string $note) {
    receiveProduct($this->product, 1, 5);

    expect(fn () => adjust($this->product, $reason, $status, $isEntry, $quantity, $note))->toThrow(DomainException::class)
        ->and(InventoryAdjustment::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe(0)
        ->and(InventoryMovement::where('type', InventoryMovementType::Adjustment)->count())->toBe(0);
})->with([
    'stock initial en sortie' => [InventoryAdjustmentReason::OpeningBalance, InventoryStatus::InStock, false, 1, 'Note'],
    'vol en entrée' => [InventoryAdjustmentReason::Theft, InventoryStatus::InStock, true, 1, 'Note'],
    'usage magasin d\'un défectueux' => [InventoryAdjustmentReason::StoreUse, InventoryStatus::DefectiveStock, false, 1, 'Note'],
    'état non physique' => [InventoryAdjustmentReason::CountCorrection, InventoryStatus::ReservedCustomer, false, 1, 'Note'],
    'note vide' => [InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 1, '  '],
    'quantité nulle' => [InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 0, 'Note'],
    'quantité insuffisante' => [InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 2, 'Note'],
]);

it('ne passe pas d\'écriture pour une valeur nulle', function () {
    $this->product->update(['cost' => Money::zero()]);

    $adjustment = adjust($this->product, InventoryAdjustmentReason::OpeningBalance, InventoryStatus::InStock, true, 2);

    expect($adjustment->movement->value)->toBeMoney(0)
        ->and(LedgerEntry::count())->toBe(0);

    expectInventoryIntegrity($this->product);
});

it('ne laisse ni modifier ni supprimer un ajustement', function () {
    $adjustment = adjust($this->product, InventoryAdjustmentReason::OpeningBalance, InventoryStatus::InStock, true, 1);

    expect(fn () => $adjustment->update(['quantity' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $adjustment->delete())->toThrow(LogicException::class);
});

it('réserve les ajustements à la permission inventory.adjust', function () {
    $manager = User::factory()->withRole('manager')->create();

    expect(fn () => app(AdjustInventory::class)->handle($manager, $this->product, testStoreId(), InventoryAdjustmentReason::OpeningBalance, InventoryStatus::InStock, true, 1, 'Note'))
        ->toThrow(AuthorizationException::class)
        ->and(InventoryAdjustment::count())->toBe(0);

    $this->actingAs($manager)->get(route('inventory.adjustments'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('inventory.adjustments'))->assertOk();
});

it('enregistre un ajustement depuis la page et garde le sens permis par le motif', function () {
    LedgerAccount::ensureSystemAccounts();

    Livewire::test(Adjustments::class)
        ->call('openAdjust')
        ->call('selectProduct', $this->product->id)
        ->set('reason', InventoryAdjustmentReason::OpeningBalance->value)
        ->assertSet('direction', 'entry')
        ->set('quantity', '3')
        ->call('adjust')
        ->assertHasErrors('note')
        ->set('note', 'Inventaire de départ')
        ->call('adjust')
        ->assertHasNoErrors()
        ->assertSet('showAdjust', false)
        ->assertSee('Stock initial')
        ->assertSee('27.00 $');

    expect(InventoryAdjustment::sole())
        ->reason->toBe(InventoryAdjustmentReason::OpeningBalance)
        ->is_entry->toBeTrue()
        ->quantity->toBe(3)
        ->note->toBe('Inventaire de départ');

    Livewire::test(Adjustments::class)
        ->call('openAdjust')
        ->call('selectProduct', $this->product->id)
        ->set('reason', InventoryAdjustmentReason::Theft->value)
        ->set('quantity', '5')
        ->set('note', 'Disparu')
        ->call('adjust')
        ->assertHasErrors('quantity')
        ->set('reason', InventoryAdjustmentReason::AnnualCountLoss->value)
        ->set('quantity', '1')
        ->call('adjust')
        ->assertHasErrors('reason')
        ->assertDontSee(InventoryAdjustmentReason::AnnualCountLoss->label());

    expect(InventoryAdjustment::count())->toBe(1);
});
