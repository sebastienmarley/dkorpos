<?php

use App\Actions\AnnualInventoryFinalUpdate;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryCountStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierType;
use App\Livewire\Inventory\Counts;
use App\Livewire\Inventory\CountShow;
use App\Models\CustomerOrder;
use App\Models\InventoryCount;
use App\Models\InventoryCountLine;
use App\Models\InventoryMovement;
use App\Models\LedgerEntry;
use App\Models\LedgerEntryLine;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
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

function countLine(InventoryCount $count, Product $product, InventoryStatus $status): InventoryCountLine
{
    return $count->lines()->where('product_id', $product->id)->where('status', $status)->sole();
}

function transfer(Product $product, InventoryStatus $from, InventoryStatus $to, int $quantity): void
{
    InventoryMovement::record($product, testStoreId(), $from, $to, $quantity, InventoryMovementType::Transfer);
}

it('remet les perdus en stock au démarrage et crée une ligne par produit et par type de compte', function () {
    receiveProduct($this->product, 6, 5);
    transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 2);
    transfer($this->product, InventoryStatus::InStock, InventoryStatus::Lost, 1);

    $count = InventoryCount::start(Carbon::parse('2026-12-31'), $this->owner, testStoreId());

    expect($this->product->inventoryStocks()->sole())
        ->quantity_lost->toBe(0)
        ->quantity_in_stock->toBe(4)
        ->and(InventoryMovement::where('type', InventoryMovementType::CountLostReturned)->sole())
        ->quantity->toBe(1)
        ->reference->is($count)->toBeTrue()
        ->and($count->lines()->get()->map(fn (InventoryCountLine $line): string => $line->status->value)->sort()->values()->all())
        ->toBe(['in_demo', 'in_stock'])
        ->and(countLine($count, $this->product, InventoryStatus::InStock)->expected())->toBe(4)
        ->and(fn () => InventoryCount::start(today(), $this->owner, testStoreId()))->toThrow(DomainException::class, 'déjà en cours');

    expectInventoryIntegrity($this->product);
});

it('gèle le stock et le perdu jusqu\'à la finalisation ou l\'abandon, sans geler les autres états', function () {
    receiveProduct($this->product, 3, 5);
    $count = InventoryCount::start(today(), $this->owner, testStoreId());

    transfer($this->product, InventoryStatus::InStock, InventoryStatus::Lost, 1);
    $count->freezeStock($this->owner);

    expect($this->product->inventoryStocks()->sole()->quantity_lost)->toBe(0)
        ->and(fn () => transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 1))->toThrow(DomainException::class, 'Le stock est gelé')
        ->and(fn () => $count->freezeStock($this->owner))->toThrow(DomainException::class);

    $count->abandon();

    transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 1);
    transfer($this->product, InventoryStatus::InDemo, InventoryStatus::DefectiveStock, 1);

    expect($count->fresh()->status)->toBe(InventoryCountStatus::Abandoned)
        ->and($this->product->inventoryStocks()->sole())->quantity_in_stock->toBe(2)->quantity_defective_stock->toBe(1)
        ->and(fn () => $count->fresh()->update(['counted_on' => today()->subDay()]))->toThrow(LogicException::class);

    expectInventoryIntegrity($this->product);
});

it('ne compte le stock qu\'une fois gelé et refuse une quantité négative', function () {
    receiveProduct($this->product, 2, 5);
    transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 1);
    $count = InventoryCount::start(today(), $this->owner, testStoreId());

    $stock = countLine($count, $this->product, InventoryStatus::InStock);
    $demo = countLine($count, $this->product, InventoryStatus::InDemo);

    $count->recordCount($demo, 1, false, 'Vu en salle', $this->owner);

    expect(fn () => $count->recordCount($stock, 1, false, null, $this->owner))->toThrow(DomainException::class, 'Gelez le stock')
        ->and(fn () => $count->recordCount($demo, -1, false, null, $this->owner))->toThrow(DomainException::class)
        ->and($demo->fresh())->counted_quantity->toBe(1)->note->toBe('Vu en salle')->counted_by->toBe($this->owner->id);
});

it('finalise : un manque devient une perte et un surplus une correction, à la date de comptage', function () {
    receiveProduct($this->product, 6, 5);
    transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 2);
    transfer($this->product, InventoryStatus::InStock, InventoryStatus::Lost, 1);

    $count = InventoryCount::start(Carbon::parse('2026-12-31'), $this->owner, testStoreId());
    $count->recordCount(countLine($count, $this->product, InventoryStatus::InDemo), 1, false, 'Démo vendue au plancher', $this->owner);
    $count->freezeStock($this->owner);
    $count->recordCount(countLine($count, $this->product, InventoryStatus::InStock), 5, false, null, $this->owner);

    app(AnnualInventoryFinalUpdate::class)->handle($count, $this->owner);

    $demo = countLine($count, $this->product, InventoryStatus::InDemo)->load('adjustment.ledgerEntry', 'adjustment.movement');
    $stock = countLine($count, $this->product, InventoryStatus::InStock)->load('adjustment.ledgerEntry', 'adjustment.movement');
    $lines = fn (LedgerEntry $entry): array => $entry->lines()->with('account')->orderBy('id')->get()
        ->map(fn (LedgerEntryLine $line): array => [$line->account->code, $line->debit->cents(), $line->credit->cents()])->all();

    expect($count->fresh())
        ->status->toBe(InventoryCountStatus::Finalized)
        ->finalized_by->toBe($this->owner->id)
        ->and($demo)->expected_quantity->toBe(2)
        ->and($demo->adjustment)
        ->reason->toBe(InventoryAdjustmentReason::AnnualCountLoss)
        ->is_entry->toBeFalse()
        ->note->toBe('Prise d\'inventaire du 2026-12-31 — Démo vendue au plancher')
        ->and($demo->adjustment->movement->value)->toBeMoney(500)
        ->and($demo->adjustment->ledgerEntry->posted_on->toDateString())->toBe('2026-12-31')
        ->and($lines($demo->adjustment->ledgerEntry))->toBe([['5110', 500, 0], ['1300', 0, 500]])
        ->and($stock)->expected_quantity->toBe(4)
        ->and($stock->adjustment->reason)->toBe(InventoryAdjustmentReason::CountCorrection)
        ->and($lines($stock->adjustment->ledgerEntry))->toBe([['1300', 900, 0], ['5120', 0, 900]])
        ->and($this->product->inventoryStocks()->sole())->quantity_in_stock->toBe(5)->quantity_in_demo->toBe(1)
        ->and(fn () => app(AnnualInventoryFinalUpdate::class)->handle($count->fresh(), $this->owner))->toThrow(DomainException::class);

    transfer($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 1);

    expectInventoryIntegrity($this->product);
});

it('refuse de finaliser sans gel, avec une ligne à compter ou un écart dans le stock client', function () {
    receiveProduct($this->product, 3, 5);
    CustomerOrder::factory()->create()->addProduct($this->product, 1);
    $count = InventoryCount::start(today(), $this->owner, testStoreId());
    $finalize = fn () => app(AnnualInventoryFinalUpdate::class)->handle($count->fresh(), $this->owner);

    expect($finalize)->toThrow(DomainException::class, 'Gelez et comptez le stock');

    $count->freezeStock($this->owner);

    expect($finalize)->toThrow(DomainException::class, '2 lignes ne sont ni comptées ni conservées.');

    $count->recordCount(countLine($count, $this->product, InventoryStatus::InStock), 2, false, null, $this->owner);
    $count->recordCount(countLine($count, $this->product, InventoryStatus::ReservedCustomer), 0, false, null, $this->owner);

    expect($finalize)->toThrow(DomainException::class, 'Écart dans le stock client')
        ->and(InventoryCount::sole()->status)->toBe(InventoryCountStatus::InProgress)
        ->and(LedgerEntry::whereNot('reference_type', (new Reception)->getMorphClass())->count())->toBe(0);

    $count->recordCount(countLine($count, $this->product, InventoryStatus::ReservedCustomer), null, true, null, $this->owner);
    $finalize();

    expect(InventoryCount::sole()->status)->toBe(InventoryCountStatus::Finalized)
        ->and(countLine($count, $this->product, InventoryStatus::ReservedCustomer))->inventory_adjustment_id->toBeNull()->expected_quantity->toBe(1)
        ->and(LedgerEntry::whereNot('reference_type', (new Reception)->getMorphClass())->count())->toBe(0);

    expectInventoryIntegrity($this->product);
});

it('laisse compter avec inventory.count, mais réserve le démarrage, le gel et la finalisation à inventory.adjust', function () {
    receiveProduct($this->product, 1, 5);
    $count = InventoryCount::start(today(), $this->owner, testStoreId());
    $manager = User::factory()->withRole('manager')->create(['store_id' => $count->store_id]);
    $line = countLine($count, $this->product, InventoryStatus::InStock);

    $this->actingAs($manager)->get(route('inventory.counts.show', $count))->assertOk();

    Livewire::actingAs($manager)->test(CountShow::class, ['count' => $count])
        ->set("notes.{$line->id}", 'Boîte au fond')
        ->call('freezeStock')
        ->assertForbidden();

    Livewire::actingAs($manager)->test(Counts::class)->call('openStart')->assertForbidden();

    expect($line->fresh()->note)->toBe('Boîte au fond')
        ->and(fn () => app(AnnualInventoryFinalUpdate::class)->handle($count, $manager))->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->withRole('salesman')->create())->get(route('inventory.counts'))->assertForbidden();
});

it('démarre, compte, finalise et exporte depuis les pages', function () {
    receiveProduct($this->product, 2, 5);

    Livewire::test(Counts::class)
        ->call('openStart')
        ->set('countedOn', '2026-12-31')
        ->call('start')
        ->assertRedirect(route('inventory.counts.show', InventoryCount::sole()));

    $count = InventoryCount::sole();
    $line = countLine($count, $this->product, InventoryStatus::InStock);

    $page = Livewire::test(CountShow::class, ['count' => $count])
        ->call('freezeStock')
        ->set("quantities.{$line->id}", '1')
        ->assertSee('-1')
        ->call('finalize')
        ->assertSee('Finalisée');

    expect($line->fresh()->adjustment->reason)->toBe(InventoryAdjustmentReason::AnnualCountLoss);

    $page->call('export')->assertFileDownloaded('inventaire_2026-12-31.csv');

    expectInventoryIntegrity($this->product);
});
