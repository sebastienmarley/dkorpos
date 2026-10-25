<?php

use App\Actions\AdjustInventory;
use App\Actions\AnnualInventoryFinalUpdate;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Livewire\Inventory\Adjustments;
use App\Livewire\Inventory\Counts;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Receptions\Create as ReceptionCreate;
use App\Models\CustomerOrder;
use App\Models\InventoryCount;
use App\Models\InventoryMovement;
use App\Models\InventoryZone;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Store;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(TaxSeeder::class);
    $this->rimouski = Store::factory()->create(['name' => 'Rimouski']);
    $this->gaspe = Store::factory()->create(['name' => 'Gaspé']);
    $this->owner = User::factory()->withRole('owner')->create(['store_id' => $this->rimouski->id]);
    $this->actingAs($this->owner);
    $this->product = Product::factory()->create(['cost' => Money::parse(40), 'clean_model' => 'SOFA1']);
});

/** Entre du stock « En stock » dans un magasin, par le journal. */
function stockIn(Product $product, Store $store, int $quantity): void
{
    InventoryMovement::record($product, $store, null, InventoryStatus::InStock, $quantity, InventoryMovementType::Adjustment);
}

/** Commande fournisseur envoyée pour un magasin, d'une ligne du produit. */
function sentOrderFor(Store $store, Product $product, int $quantity): SupplierOrderLine
{
    $order = SupplierOrder::factory()->create(['store_id' => $store->id, 'supplier_id' => $product->supplier_id]);
    $line = SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => Money::parse(40)]);
    $order->markPending();
    $order->send();

    return $line->fresh();
}

it('montre les quantités du produit par magasin et le total de l\'entreprise', function () {
    stockIn($this->product, $this->rimouski, 3);
    stockIn($this->product, $this->gaspe, 2);
    InventoryMovement::record($this->product, $this->gaspe, InventoryStatus::InStock, InventoryStatus::InDemo, 1, InventoryMovementType::Transfer);

    Livewire::test(ProductShow::class, ['product' => $this->product->fresh()])
        ->assertSee('Rimouski')
        ->assertSee('Gaspé')
        ->assertSee('Total de l\'entreprise');

    expect($this->product->stockAt($this->rimouski->id)->quantity_in_stock)->toBe(3)
        ->and($this->product->stockAt($this->gaspe->id))
        ->quantity_in_stock->toBe(1)
        ->quantity_in_demo->toBe(1)
        ->and($this->product->totalQuantity(InventoryStatus::InStock))->toBe(4);

    expectInventoryIntegrity($this->product);
});

describe('réception par magasin', function () {
    it('n\'offre pas au commis le fournisseur ni les lignes d\'une commande d\'un autre magasin', function () {
        $line = sentOrderFor($this->gaspe, $this->product, 1);
        $clerk = User::factory()->withRole('warehouse')->create(['store_id' => $this->rimouski->id]);

        Livewire::actingAs($clerk)->test(ReceptionCreate::class)
            ->assertViewHas('suppliers', fn ($suppliers): bool => ! $suppliers->contains('id', $line->order->supplier_id))
            ->set('supplierId', (string) $line->order->supplier_id)
            ->set("selected.{$line->id}", true)
            ->assertSet('selected', [])
            ->assertSet('showStoreConfirm', false);
    });

    it('demande confirmation avant de recevoir la commande d\'un autre magasin avec stores.all', function () {
        $line = sentOrderFor($this->gaspe, $this->product, 2);

        Livewire::test(ReceptionCreate::class)
            ->set('supplierId', (string) $line->order->supplier_id)
            ->set('orderSearch', $line->order->number)
            ->assertSee('Magasin : Gaspé')
            ->set("selected.{$line->id}", true)
            ->assertSet('selected', [])
            ->assertSet('showStoreConfirm', true)
            ->assertSee('est celle du magasin Gaspé')
            ->call('confirmOtherStore')
            ->assertSet("selected.{$line->id}", true)
            ->assertSet("quantities.{$line->id}", '2')
            ->call('start');

        expect(Reception::sole()->store_id)->toBe($this->gaspe->id);
    });

    it('ne coche pas la ligne quand l\'usager annule la confirmation', function () {
        $line = sentOrderFor($this->gaspe, $this->product, 2);

        Livewire::test(ReceptionCreate::class)
            ->set('supplierId', (string) $line->order->supplier_id)
            ->set("selected.{$line->id}", true)
            ->call('cancelOtherStore')
            ->assertSet('selected', [])
            ->assertSet('showStoreConfirm', false)
            ->call('start');

        expect(Reception::count())->toBe(0);
    });

    it('ne demande pas de confirmation pour une commande de son magasin', function () {
        $line = sentOrderFor($this->rimouski, $this->product, 1);

        Livewire::test(ReceptionCreate::class)
            ->set('supplierId', (string) $line->order->supplier_id)
            ->set("selected.{$line->id}", true)
            ->assertSet('showStoreConfirm', false)
            ->assertSet("selected.{$line->id}", true);
    });

    it('laisse un usager qui a accès à tous les magasins recevoir pour un autre magasin', function () {
        $line = sentOrderFor($this->gaspe, $this->product, 2);

        Reception::start($line->order->supplier, [$line->id => ['quantity' => 2]], ['received_by' => $this->owner->id])->complete();

        expect(Reception::sole()->store_id)->toBe($this->gaspe->id)
            ->and($this->product->stockAt($this->gaspe->id)->quantity_in_stock)->toBe(2);
    });

    it('fait entrer la marchandise dans le stock du magasin de destination de la commande', function () {
        $line = sentOrderFor($this->rimouski, $this->product, 4);

        Reception::start($line->order->supplier, [$line->id => ['quantity' => 4]], ['received_by' => $this->owner->id])->complete();

        expect(Reception::sole()->store_id)->toBe($this->rimouski->id)
            ->and($this->product->stockAt($this->rimouski->id)->quantity_in_stock)->toBe(4)
            ->and($this->product->stockAt($this->gaspe->id))->toBeNull();

        expectInventoryIntegrity($this->product);
    });

    it('refuse de recevoir une commande destinée à un autre magasin', function () {
        $line = sentOrderFor($this->gaspe, $this->product, 1);
        $clerk = User::factory()->withRole('warehouse')->create(['store_id' => $this->rimouski->id]);

        expect(fn () => Reception::start($line->order->supplier, [$line->id => ['quantity' => 1]], ['received_by' => $clerk->id]))
            ->toThrow(DomainException::class, 'Cette commande est destinée à un autre magasin');

        Livewire::actingAs($clerk)->test(ReceptionCreate::class)
            ->set('supplierId', (string) $line->order->supplier_id)
            ->set('orderSearch', $line->order->number)
            ->assertDontSee($line->order->number);
    });
});

describe('ventes par magasin', function () {
    it('réserve dans le stock de l\'entrepôt du magasin de la commande, et commande pour cet entrepôt', function () {
        $this->gaspe->update(['warehouse_store_id' => $this->rimouski->id]);
        stockIn($this->product, $this->rimouski, 1);
        $order = CustomerOrder::factory()->create(['store_id' => $this->gaspe->id]);

        $line = $order->addProduct($this->product, 2);

        expect($order->stockStoreId())->toBe($this->rimouski->id)
            ->and($line->quantity_reserved)->toBe(1)
            ->and($line->quantity_on_order)->toBe(1)
            ->and($this->product->stockAt($this->rimouski->id)->quantity_reserved)->toBe(1)
            ->and($line->supplierOrderLine->order->store_id)->toBe($this->rimouski->id);
    });

    it('n\'utilise pas le stock d\'un autre magasin', function () {
        stockIn($this->product, $this->gaspe, 5);
        $order = CustomerOrder::factory()->create(['store_id' => $this->rimouski->id]);

        $line = $order->addProduct($this->product, 1);

        expect($line->quantity_reserved)->toBe(0)
            ->and($line->quantity_on_order)->toBe(1)
            ->and($this->product->stockAt($this->gaspe->id)->quantity_in_stock)->toBe(5);
    });

    it('garde un brouillon de commande par fournisseur et par magasin', function () {
        CustomerOrder::factory()->create(['store_id' => $this->rimouski->id])->addProduct($this->product, 1);
        CustomerOrder::factory()->create(['store_id' => $this->gaspe->id])->addProduct($this->product, 1);

        expect(SupplierOrder::where('supplier_id', $this->product->supplier_id)->pluck('store_id')->sort()->values()->all())
            ->toBe(collect([$this->rimouski->id, $this->gaspe->id])->sort()->values()->all());
    });
});

describe('prise d\'inventaire par magasin', function () {
    it('démarre une prise par magasin à la fois, et propose seulement les zones du magasin compté', function () {
        InventoryZone::factory()->create(['store_id' => $this->rimouski->id, 'name' => 'Allée R']);
        InventoryZone::factory()->create(['store_id' => $this->gaspe->id, 'name' => 'Allée G']);

        $rimouski = InventoryCount::start(today(), $this->owner, $this->rimouski);
        $gaspe = InventoryCount::start(today(), $this->owner, $this->gaspe);
        $rimouski->prepareSheets();

        expect(fn () => InventoryCount::start(today(), $this->owner, $this->rimouski))->toThrow(DomainException::class, 'déjà en cours pour ce magasin')
            ->and($gaspe->store_id)->toBe($this->gaspe->id)
            ->and($rimouski->sheets()->whereNotNull('inventory_zone_id')->with('zone')->get()->pluck('zone.name')->unique()->values()->all())->toBe(['Allée R']);
    });

    it('gèle seulement le stock du magasin compté', function () {
        stockIn($this->product, $this->rimouski, 2);
        stockIn($this->product, $this->gaspe, 2);
        InventoryCount::start(today(), $this->owner, $this->rimouski)->freezeStock($this->owner);

        expect(fn () => InventoryMovement::record($this->product, $this->rimouski, InventoryStatus::InStock, InventoryStatus::InDemo, 1, InventoryMovementType::Transfer))
            ->toThrow(DomainException::class, 'Le stock est gelé');

        InventoryMovement::record($this->product, $this->gaspe, InventoryStatus::InStock, InventoryStatus::InDemo, 1, InventoryMovementType::Transfer);

        expect($this->product->stockAt($this->gaspe->id)->quantity_in_demo)->toBe(1);
    });

    it('finalise un magasin : attendu, ajustements et écritures de ce magasin seulement', function () {
        stockIn($this->product, $this->rimouski, 5);
        stockIn($this->product, $this->gaspe, 7);

        $count = InventoryCount::start(Carbon::parse('2026-10-20'), $this->owner, $this->rimouski);
        $count->freezeStock($this->owner);
        $line = $count->lines()->where('status', InventoryStatus::InStock)->sole();

        expect($line->expected())->toBe(5);

        $count->recordCount($line, 3, false, null, $this->owner);
        app(AnnualInventoryFinalUpdate::class)->handle($count, $this->owner);

        $adjustment = $line->fresh()->adjustment;

        expect($this->product->stockAt($this->rimouski->id)->quantity_in_stock)->toBe(3)
            ->and($this->product->stockAt($this->gaspe->id)->quantity_in_stock)->toBe(7)
            ->and($adjustment->store_id)->toBe($this->rimouski->id)
            ->and(LedgerEntry::query()->whereMorphedTo('reference', $adjustment)->sole())
            ->store_id->toBe($this->rimouski->id)
            ->posted_on->toDateString()->toBe('2026-10-20');

        expectInventoryIntegrity($this->product);
    });
});

describe('année fiscale', function () {
    it('commence le 1er février', function () {
        expect(InventoryCount::fiscalYearStart(Carbon::parse('2026-01-31'))->toDateString())->toBe('2025-02-01')
            ->and(InventoryCount::fiscalYearStart(Carbon::parse('2026-02-01'))->toDateString())->toBe('2026-02-01');
    });

    it('montre la dernière prise de chaque magasin, et avertit s\'il n\'en a pas eu cette année', function () {
        $this->travelTo('2026-10-24');
        $count = InventoryCount::start(Carbon::parse('2026-03-15'), $this->owner, $this->rimouski);
        $count->freezeStock($this->owner);
        app(AnnualInventoryFinalUpdate::class)->handle($count->fresh(), $this->owner);

        $status = InventoryCount::fiscalYearStatus()->keyBy(fn (array $entry): string => $entry['store']->name);

        expect($status['Rimouski']['isCurrent'])->toBeTrue()
            ->and($status['Gaspé']['isCurrent'])->toBeFalse();

        Livewire::test(Counts::class)
            ->assertSee('Année fiscale depuis le '.Carbon::parse('2026-02-01')->translatedFormat('j F Y'))
            ->assertSee('Faite cette année')
            ->assertSee('À faire cette année');
    });

    it('démarre la prise du magasin choisi', function () {
        Livewire::test(Counts::class)
            ->call('openStart')
            ->assertSet('storeId', (string) $this->rimouski->id)
            ->set('storeId', (string) $this->gaspe->id)
            ->call('start')
            ->assertHasNoErrors();

        expect(InventoryCount::sole()->store_id)->toBe($this->gaspe->id);
    });
});

it('ajuste le stock du magasin choisi et passe l\'écriture de ce magasin', function () {
    stockIn($this->product, $this->gaspe, 2);

    Livewire::test(Adjustments::class)
        ->call('openAdjust')
        ->assertSet('storeId', (string) $this->rimouski->id)
        ->call('selectProduct', $this->product->id)
        ->set('storeId', (string) $this->gaspe->id)
        ->set('reason', InventoryAdjustmentReason::Breakage->value)
        ->set('direction', 'exit')
        ->set('status', InventoryStatus::InStock->value)
        ->set('quantity', '1')
        ->set('note', 'Échappé')
        ->call('adjust')
        ->assertHasNoErrors();

    expect($this->product->stockAt($this->gaspe->id)->quantity_in_stock)->toBe(1)
        ->and(LedgerEntry::query()->latest('id')->first()->store_id)->toBe($this->gaspe->id);

    expect(fn () => app(AdjustInventory::class)->handle($this->owner, $this->product, $this->rimouski, InventoryAdjustmentReason::Breakage, InventoryStatus::InStock, false, 1, 'Rien ici'))
        ->toThrow(DomainException::class, 'Quantité insuffisante');
});
