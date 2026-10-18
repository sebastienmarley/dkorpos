<?php

use App\Actions\AnnualInventoryFinalUpdate;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryCountPass;
use App\Enums\InventoryCountResultSource;
use App\Enums\InventoryCountSheetState;
use App\Enums\InventoryCountStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\SupplierType;
use App\Livewire\Inventory\CountSheets;
use App\Livewire\Inventory\MyCountSheets;
use App\Livewire\Inventory\Zones;
use App\Models\InventoryCount;
use App\Models\InventoryCountResult;
use App\Models\InventoryCountSheet;
use App\Models\InventoryMovement;
use App\Models\InventoryZone;
use App\Models\Product;
use App\Models\ProductUpc;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->owner = User::factory()->withRole('owner')->create();
    $this->actingAs($this->owner);
    $this->counterA = User::factory()->withRole('warehouse')->create();
    $this->counterB = User::factory()->withRole('warehouse')->create();
    $this->counterC = User::factory()->withRole('warehouse')->create();
    $this->product = Product::factory()->create([
        'supplier_id' => Supplier::factory()->create(['type' => SupplierType::Product])->id,
        'cost' => Money::parse(9),
    ]);
    ProductUpc::create(['product_id' => $this->product->id, 'upc' => '111']);
});

function sheetOf(InventoryCount $count, InventoryStatus $status, ?InventoryZone $zone, InventoryCountPass $pass): InventoryCountSheet
{
    return $count->sheetsFor($status, $zone?->id)->where('pass', $pass)->sole();
}

/**
 * Attribue la feuille, la commence, lit les codes (code => quantité) et la termine.
 *
 * @param  array<string, int>  $scans
 */
function countSheet(InventoryCountSheet $sheet, User $counter, array $scans = []): void
{
    $sheet->inventoryCount->assignSheet($sheet, $counter);
    $sheet->refresh()->start($counter);

    foreach ($scans as $code => $quantity) {
        $sheet->scan($counter, (string) $code, $quantity);
    }

    $sheet->finish($counter);
}

it('gère les zones permanentes et prépare les feuilles A et B des types et des zones actives', function () {
    Livewire::test(Zones::class)
        ->call('openCreate')
        ->set('name', 'Allée 1')
        ->call('save')
        ->call('openCreate')
        ->set('name', 'Allée 1')
        ->call('save')
        ->assertHasErrors('name')
        ->set('name', 'Mezzanine')
        ->call('save')
        ->call('toggleActive', InventoryZone::where('name', 'Mezzanine')->sole()->id);

    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $count->prepareSheets();

    expect($count->sheets()->count())->toBe(8)
        ->and($count->sheets()->whereNotNull('inventory_zone_id')->pluck('inventory_zone_id')->unique()->all())->toBe([InventoryZone::where('name', 'Allée 1')->sole()->id])
        ->and(sheetOf($count, InventoryStatus::InStock, InventoryZone::first(), InventoryCountPass::B)->areaLabel())->toBe('Stock — Allée 1');

    $this->actingAs($this->counterA)->get(route('inventory.zones'))->assertForbidden();
});

it('fait compter chaque portée par deux personnes différentes', function () {
    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $a = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A);
    $b = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::B);

    $count->assignSheet($a, $this->counterA);

    expect(fn () => $count->assignSheet($b, $this->counterA))->toThrow(DomainException::class, 'personnes différentes')
        ->and(fn () => $count->assignSheet($b, User::factory()->withRole('salesman')->create()))->toThrow(DomainException::class, 'ne peut pas compter');

    $count->assignSheet(sheetOf($count, InventoryStatus::DefectiveStock, null, InventoryCountPass::A), $this->counterA);
    $count->assignSheet($b, $this->counterB);

    expect($b->fresh()->assigned_to)->toBe($this->counterB->id);
});

it('lit les codes à la douchette, annule, corrige et consigne les codes inconnus', function () {
    $zone = InventoryZone::factory()->create();
    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $demo = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A);
    $stock = sheetOf($count, InventoryStatus::InStock, $zone, InventoryCountPass::A);
    $count->assignSheet($demo, $this->counterA);
    $count->assignSheet($stock, $this->counterA);

    expect(fn () => $stock->start($this->counterA))->toThrow(DomainException::class, 'une fois gelé')
        ->and(fn () => $demo->start($this->counterB))->toThrow(DomainException::class, 'pas attribuée')
        ->and(fn () => $demo->scan($this->counterA, '111'))->toThrow(DomainException::class, 'Commencez');

    $demo->start($this->counterA);
    $demo->scan($this->counterA, '111');
    $demo->scan($this->counterA, ' 111 ', 5);
    $unknown = $demo->scan($this->counterA, '999');

    expect($demo->quantities())->toBe([$this->product->id => 6])
        ->and($unknown->isUnknown())->toBeTrue();

    $demo->undoLastScan($this->counterA);
    $demo->undoLastScan($this->counterA);
    $demo->setQuantity($this->counterA, $this->product, 4);

    expect($demo->quantities())->toBe([$this->product->id => 4])
        ->and($demo->scans()->whereNull('code')->sole()->quantity)->toBe(3)
        ->and($unknown->fresh()->undone_at)->not->toBeNull();

    $demo->finish($this->counterA);

    expect($demo->fresh()->state)->toBe(InventoryCountSheetState::Done)
        ->and(fn () => $demo->scan($this->counterA, '111'))->toThrow(DomainException::class, 'terminée');
});

it('associe un code inconnu à un produit, par un superviseur seulement', function () {
    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $demo = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A);
    $count->assignSheet($demo, $this->counterA);
    $demo->start($this->counterA);
    $unknown = $demo->scan($this->counterA, '222', 2);

    expect(fn () => $demo->associate($unknown, $this->product, $this->counterA))->toThrow(DomainException::class, 'superviseur');

    $demo->associate($unknown, $this->product, User::factory()->withRole('manager')->create());

    expect(ProductUpc::where('upc', '222')->sole()->product_id)->toBe($this->product->id)
        ->and($demo->quantities())->toBe([$this->product->id => 2])
        ->and($unknown->fresh()->product_id)->toBe($this->product->id);

    $demo->scan($this->counterA, '222');

    expect($demo->quantities())->toBe([$this->product->id => 3]);
});

it('compare, recompte les désaccords, réconcilie et alimente les lignes de la prise', function () {
    receiveProduct($this->product, 10, 5);
    InventoryMovement::record($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 2, InventoryMovementType::Transfer);
    $zone1 = InventoryZone::factory()->create(['name' => 'Allée 1', 'sort_order' => 1]);
    $zone2 = InventoryZone::factory()->create(['name' => 'Allée 2', 'sort_order' => 2]);

    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $count->freezeStock($this->owner);

    countSheet(sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A), $this->counterA, ['111' => 2]);
    countSheet(sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::B), $this->counterB, ['111' => 2]);
    countSheet(sheetOf($count, InventoryStatus::InStock, $zone1, InventoryCountPass::A), $this->counterA, ['111' => 4]);
    countSheet(sheetOf($count, InventoryStatus::InStock, $zone1, InventoryCountPass::B), $this->counterB, ['111' => 4]);
    countSheet(sheetOf($count, InventoryStatus::InStock, $zone2, InventoryCountPass::A), $this->counterA, ['111' => 3]);
    countSheet(sheetOf($count, InventoryStatus::InStock, $zone2, InventoryCountPass::B), $this->counterB, ['111' => 5]);

    foreach ([InventoryStatus::DefectiveStock, InventoryStatus::ReservedCustomer] as $status) {
        countSheet(sheetOf($count, $status, null, InventoryCountPass::A), $this->counterA);
        countSheet(sheetOf($count, $status, null, InventoryCountPass::B), $this->counterB);
        $count->reconcileScope($status, null, $this->owner);
    }

    $finalize = fn () => app(AnnualInventoryFinalUpdate::class)->handle($count->fresh(), $this->owner);

    expect($count->compareScope(InventoryStatus::InStock, $zone2->id))->toBe([
        ['product_id' => $this->product->id, 'a' => 3, 'b' => 5, 'recount' => null, 'agreed' => false],
    ])
        ->and(fn () => $count->reconcileScope(InventoryStatus::InStock, $zone2->id, $this->owner))->toThrow(DomainException::class, 'Créez le recompte')
        ->and(fn () => $count->createRecount(InventoryStatus::InStock, $zone2->id, $this->counterA))->toThrow(DomainException::class, 'troisième compteur')
        ->and($finalize)->toThrow(DomainException::class, 'Portées à réconcilier avant de finaliser : Démonstrateur, Stock — Allée 1, Stock — Allée 2.');

    $recount = $count->createRecount(InventoryStatus::InStock, $zone2->id, $this->counterC);
    $other = Product::factory()->create(['supplier_id' => $this->product->supplier_id]);
    ProductUpc::create(['product_id' => $other->id, 'upc' => '333']);
    $recount->start($this->counterC);

    expect(fn () => $recount->scan($this->counterC, '333'))->toThrow(DomainException::class, 'pas à recompter');

    $recount->scan($this->counterC, '111', 3);
    $recount->finish($this->counterC);

    $count->reconcileScope(InventoryStatus::InStock, $zone2->id, $this->owner);
    $count->reconcileScope(InventoryStatus::InStock, $zone1->id, $this->owner);
    $count->reconcileScope(InventoryStatus::InDemo, null, $this->owner);

    $stockLine = $count->lines()->where('product_id', $this->product->id)->where('status', InventoryStatus::InStock)->sole();

    expect(InventoryCountResult::where('inventory_zone_id', $zone2->id)->sole()->source)->toBe(InventoryCountResultSource::Recount)
        ->and($stockLine->counted_quantity)->toBe(7)
        ->and($count->lines()->where('status', InventoryStatus::InDemo)->sole()->counted_quantity)->toBe(2)
        ->and(fn () => $count->recordCount($stockLine, 8, false, null, $this->owner))->toThrow(DomainException::class, 'feuilles de compte')
        ->and(fn () => $count->reconcileScope(InventoryStatus::InDemo, null, $this->owner))->toThrow(DomainException::class, 'déjà réconciliée');

    $count->recordCount($stockLine, 7, false, 'Palette au fond', $this->owner);
    $finalize();

    expect($count->fresh()->status)->toBe(InventoryCountStatus::Finalized)
        ->and($stockLine->fresh()->adjustment)
        ->reason->toBe(InventoryAdjustmentReason::AnnualCountLoss)
        ->quantity->toBe(1)
        ->and($this->product->inventoryStock()->sole()->quantity_in_stock)->toBe(7);

    expectInventoryIntegrity($this->product);
});

it('retient le recompte qui ne concorde ni avec A ni avec B seulement sur décision du superviseur', function () {
    receiveProduct($this->product, 5, 5);
    InventoryMovement::record($this->product, InventoryStatus::InStock, InventoryStatus::InDemo, 5, InventoryMovementType::Transfer);
    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();

    countSheet(sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A), $this->counterA, ['111' => 3]);
    countSheet(sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::B), $this->counterB, ['111' => 5]);
    countSheet($count->createRecount(InventoryStatus::InDemo, null, $this->counterC), $this->counterC, ['111' => 4]);

    expect(fn () => $count->reconcileScope(InventoryStatus::InDemo, null, $this->owner))->toThrow(DomainException::class, 'retenez le recompte');

    $count->reconcileScope(InventoryStatus::InDemo, null, $this->owner, acceptRecount: true);

    expect(InventoryCountResult::sole())->quantity->toBe(4)->source->toBe(InventoryCountResultSource::Supervisor)
        ->and($count->lines()->where('status', InventoryStatus::InDemo)->sole()->counted_quantity)->toBe(4);
});

it('montre à chaque compteur ses feuilles et le fait compter sur la page mobile', function () {
    $count = InventoryCount::start(today(), $this->owner);
    $count->prepareSheets();
    $demo = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A);
    $count->assignSheet($demo, $this->counterA);
    $count->assignSheet(sheetOf($count, InventoryStatus::DefectiveStock, null, InventoryCountPass::B), $this->counterB);

    Livewire::actingAs($this->counterA)->test(MyCountSheets::class)
        ->assertSee('Démonstrateur')
        ->assertDontSee('Défectueux')
        ->call('select', $demo->id)
        ->call('start')
        ->set('code', '111')
        ->call('scan')
        ->assertSet('code', '')
        ->assertSee('+1')
        ->set('quantity', '3')
        ->set('code', '111')
        ->call('scan')
        ->set('code', '999')
        ->call('scan')
        ->assertSee('Code inconnu : 999')
        ->assertSee('mise de côté')
        ->set("quantities.{$this->product->id}", '6')
        ->call('finish');

    expect($demo->fresh())->state->toBe(InventoryCountSheetState::Done)
        ->and($demo->quantities())->toBe([$this->product->id => 6]);

    Livewire::actingAs($this->counterB)->test(MyCountSheets::class)
        ->call('select', $demo->id)
        ->assertNotFound();
});

it('réserve la supervision des feuilles à inventory.supervise et y attribue les comptes', function () {
    $count = InventoryCount::start(today(), $this->owner);
    $manager = User::factory()->withRole('manager')->create();

    $this->actingAs($this->counterA)->get(route('inventory.counts.sheets', $count))->assertForbidden();
    $this->actingAs($manager)->get(route('inventory.counts.sheets', $count))->assertOk();

    $page = Livewire::actingAs($manager)->test(CountSheets::class, ['count' => $count])->call('prepare');
    $demoA = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::A);
    $demoB = sheetOf($count, InventoryStatus::InDemo, null, InventoryCountPass::B);

    $page->set("assignments.{$demoA->id}", (string) $this->counterA->id)
        ->set("assignments.{$demoB->id}", (string) $this->counterA->id)
        ->assertSee('Démonstrateur');

    expect($demoA->fresh()->assigned_to)->toBe($this->counterA->id)
        ->and($demoB->fresh()->assigned_to)->toBeNull();
});
