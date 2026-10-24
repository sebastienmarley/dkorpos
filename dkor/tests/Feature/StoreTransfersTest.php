<?php

use App\Actions\AcceptStoreTransfer;
use App\Actions\CloseStoreTransfer;
use App\Actions\ReceiveStoreTransfer;
use App\Actions\RequestStoreTransfer;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\StoreTransferStatus;
use App\Livewire\CustomerOrders\LineTransfer;
use App\Livewire\Inventory\Transfers;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryCount;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\User;
use App\Notifications\StoreTransferNotice;
use App\Support\Money;
use Database\Seeders\TaxSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(TaxSeeder::class);
    $this->rimouski = Store::factory()->create(['name' => 'Rimouski']);
    $this->gaspe = Store::factory()->create(['name' => 'Gaspé']);
    $this->salesman = User::factory()->withRole('salesman')->create(['store_id' => $this->rimouski->id]);
    $this->clerkRimouski = User::factory()->withRole('warehouse')->create(['store_id' => $this->rimouski->id]);
    $this->clerkGaspe = User::factory()->withRole('warehouse')->create(['store_id' => $this->gaspe->id]);
    $this->product = Product::factory()->create(['cost' => Money::parse(40), 'model' => 'SOFA-1']);
    $this->actingAs($this->salesman);
});

function stockAtStore(Product $product, Store $store, int $quantity, InventoryStatus $status = InventoryStatus::InStock): void
{
    InventoryMovement::record($product, $store, null, $status, $quantity, InventoryMovementType::Adjustment);
}

function stockOf(Product $product, Store $store, InventoryStatus $status): int
{
    return $product->stockAt($store->id)?->quantityFor($status) ?? 0;
}

describe('transfert de stock', function () {
    it('passe en transfert, en transit, puis en stock chez le magasin qui reçoit, avec ses unités et leur coût', function () {
        stockAtStore($this->product, $this->gaspe, 3);
        $cost = InventoryUnit::where('store_id', $this->gaspe->id)->first()->cost;

        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 2);

        expect($transfer)
            ->from_store_id->toBe($this->gaspe->id)
            ->to_store_id->toBe($this->rimouski->id)
            ->status->toBe(StoreTransferStatus::Requested)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InStock))->toBe(1)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InTransfer))->toBe(2);

        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);

        expect(stockOf($this->product, $this->gaspe, InventoryStatus::InTransit))->toBe(2)
            ->and($transfer->fresh()->status)->toBe(StoreTransferStatus::InTransit);

        app(ReceiveStoreTransfer::class)->handle($this->clerkRimouski, $transfer, 2);

        $out = InventoryMovement::where('type', InventoryMovementType::TransferSent)->sole();
        $in = InventoryMovement::where('type', InventoryMovementType::TransferReceived)->sole();

        expect(stockOf($this->product, $this->rimouski, InventoryStatus::InStock))->toBe(2)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InTransit))->toBe(0)
            ->and(InventoryUnit::where('store_id', $this->rimouski->id)->inHand()->count())->toBe(2)
            ->and($out->store_id)->toBe($this->gaspe->id)
            ->and($in->store_id)->toBe($this->rimouski->id)
            ->and($in->value)->toBeMoney($cost->times(2)->cents())
            ->and($out->value->equals($in->value))->toBeTrue()
            ->and($transfer->fresh())
            ->status->toBe(StoreTransferStatus::Received)
            ->quantity_received->toBe(2);

        expectInventoryIntegrity($this->product);
    });

    it('fait un perdu chez le magasin qui reçoit de ce qui manque à la réception', function () {
        stockAtStore($this->product, $this->gaspe, 2);
        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 2);
        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);

        app(ReceiveStoreTransfer::class)->handle($this->clerkRimouski, $transfer, 1);

        expect(stockOf($this->product, $this->rimouski, InventoryStatus::InStock))->toBe(1)
            ->and(stockOf($this->product, $this->rimouski, InventoryStatus::Lost))->toBe(1);

        expectInventoryIntegrity($this->product);
    });

    it('refuse un transfert plus grand que le stock du magasin qui expédie', function () {
        stockAtStore($this->product, $this->gaspe, 1);

        expect(fn () => app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 2))
            ->toThrow(DomainException::class, '(1 disponible, 2 demandé)');
    });

    it('ne transfère pas un démonstrateur pour le stock', function () {
        stockAtStore($this->product, $this->gaspe, 1, InventoryStatus::InDemo);

        expect(fn () => app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1, source: InventoryStatus::InDemo))
            ->toThrow(DomainException::class, 'un démonstrateur ne se transfère que pour un client');
    });

    it('rend la marchandise au magasin qui expédie quand il refuse, ou quand le demandeur annule', function () {
        stockAtStore($this->product, $this->gaspe, 2);
        $refused = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1);
        $cancelled = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1);

        expect(fn () => app(CloseStoreTransfer::class)->handle($this->clerkGaspe, $refused, true))->toThrow(DomainException::class, 'raison');

        $this->actingAs($this->clerkGaspe);
        app(CloseStoreTransfer::class)->handle($this->clerkGaspe, $refused, true, 'Réservé pour un client');
        $this->actingAs($this->salesman);
        app(CloseStoreTransfer::class)->handle($this->salesman, $cancelled, false);

        expect($refused->fresh()->status)->toBe(StoreTransferStatus::Refused)
            ->and($cancelled->fresh()->status)->toBe(StoreTransferStatus::Cancelled)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InStock))->toBe(2)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InTransfer))->toBe(0)
            ->and($this->salesman->notifications()->where('type', StoreTransferNotice::Type)->pluck('data')->pluck('title')->implode(' | '))->toContain('Transfert refusé par Gaspé : Réservé pour un client');
    });

    it('réserve chaque étape au bon magasin', function () {
        stockAtStore($this->product, $this->gaspe, 1);
        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1);

        expect(fn () => app(AcceptStoreTransfer::class)->handle($this->clerkRimouski, $transfer))->toThrow(DomainException::class, 'Seul le magasin qui expédie')
            ->and(fn () => app(RequestStoreTransfer::class)->handle($this->clerkGaspe, $this->product, $this->gaspe->id, 1))->toThrow(DomainException::class, 'un autre magasin');

        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);

        expect(fn () => app(ReceiveStoreTransfer::class)->handle($this->clerkGaspe, $transfer, 1))->toThrow(DomainException::class, 'Seul le magasin qui reçoit')
            ->and(fn () => app(CloseStoreTransfer::class)->handle($this->salesman, $transfer, false))->toThrow(DomainException::class, 'pas encore en transit');
    });

    it('attend la fin du gel du stock d\'un des deux magasins', function () {
        stockAtStore($this->product, $this->gaspe, 1);
        InventoryCount::start(today(), User::factory()->withRole('owner')->create(['store_id' => $this->gaspe->id]), $this->gaspe)->freezeStock($this->clerkGaspe);

        expect(fn () => app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1))
            ->toThrow(DomainException::class, 'gelé');
    });
});

describe('transfert pour un client', function () {
    beforeEach(function () {
        $this->order = CustomerOrder::factory()->create(['store_id' => $this->rimouski->id]);
        $this->order->syncSalespeople([['user_id' => $this->salesman->id, 'percent' => 100]]);
        $this->line = $this->order->addProduct($this->product, 1);
    });

    it('sert la ligne par un autre magasin plutôt que par le fournisseur, jusqu\'à « Transfert reçu »', function () {
        stockAtStore($this->product, $this->gaspe, 1);
        expect($this->line->supplier_order_line_id)->not->toBeNull();

        Livewire::test(LineTransfer::class, ['lineId' => $this->line->id])
            ->assertSee('Gaspé — en stock : 1')
            ->call('request')
            ->assertHasNoErrors();

        $transfer = StoreTransfer::sole();
        $line = $transfer->line;

        expect($line->supplier_order_line_id)->toBeNull()
            ->and($line->status)->toBe(CustomerOrderLineStatus::OnOrder)
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::CustomerInTransfer))->toBe(1)
            ->and(fn () => $this->order->adjustLine($line, 0, 2))->toThrow(DomainException::class, 'en transfert');

        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);
        expect(stockOf($this->product, $this->gaspe, InventoryStatus::CustomerInTransit))->toBe(1);

        $this->actingAs($this->clerkRimouski);
        app(ReceiveStoreTransfer::class)->handle($this->clerkRimouski, $transfer, 1);

        expect($line->fresh())
            ->status->toBe(CustomerOrderLineStatus::TransferReceived)
            ->quantity_reserved->toBe(1)
            ->quantity_on_order->toBe(0)
            ->and(stockOf($this->product, $this->rimouski, InventoryStatus::ReservedCustomer))->toBe(1)
            ->and($this->order->pickableQuantities())->toBe([$line->id => 1])
            ->and($this->salesman->notifications()->where('type', StoreTransferNotice::Type)->pluck('data')->pluck('title')->implode(' | '))->toContain('l\'article du client est prêt');

        expectInventoryIntegrity($this->product);
    });

    it('transfère la dernière unité en démo d\'un autre magasin, réservée au client à l\'arrivée', function () {
        stockAtStore($this->product, $this->gaspe, 1, InventoryStatus::InDemo);

        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1, $this->line, InventoryStatus::InDemo);
        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);
        app(ReceiveStoreTransfer::class)->handle($this->clerkRimouski, $transfer, 1);

        expect(stockOf($this->product, $this->gaspe, InventoryStatus::InDemo))->toBe(0)
            ->and(stockOf($this->product, $this->rimouski, InventoryStatus::ReservedCustomer))->toBe(1)
            ->and(stockOf($this->product, $this->rimouski, InventoryStatus::InDemo))->toBe(0);

        expectInventoryIntegrity($this->product);
    });

    it('commande au fournisseur une ligne dont le transfert est refusé, et avise le vendeur', function () {
        stockAtStore($this->product, $this->gaspe, 1);
        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1, $this->line);

        $this->actingAs($this->clerkGaspe);
        app(CloseStoreTransfer::class)->handle($this->clerkGaspe, $transfer, true, 'Vendu ce matin');

        expect($transfer->line->fresh()->supplier_order_line_id)->not->toBeNull()
            ->and(stockOf($this->product, $this->gaspe, InventoryStatus::InStock))->toBe(1)
            ->and($this->salesman->notifications()->where('type', StoreTransferNotice::Type)->pluck('data')->pluck('title')->implode(' | '))->toContain('prévenez le client du délai');

        expectInventoryIntegrity($this->product);
    });

    it('commande au fournisseur ce qui manque à la réception d\'un transfert pour un client', function () {
        $line = $this->order->addProduct(Product::factory()->create(), 1);
        $this->order->adjustLine($this->line, 0, 2);
        stockAtStore($this->product, $this->gaspe, 2);
        $transfer = app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 2, $this->line->fresh());
        app(AcceptStoreTransfer::class)->handle($this->clerkGaspe, $transfer);

        app(ReceiveStoreTransfer::class)->handle($this->clerkRimouski, $transfer, 1);

        $reordered = CustomerOrderLine::where('product_id', $this->product->id)->where('status', CustomerOrderLineStatus::OnOrder)->sole();

        expect($transfer->line->fresh()->quantity_reserved)->toBe(1)
            ->and($reordered->quantity_on_order)->toBe(1)
            ->and($reordered->supplier_order_line_id)->not->toBeNull()
            ->and(stockOf($this->product, $this->rimouski, InventoryStatus::Lost))->toBe(1);

        expectInventoryIntegrity($this->product);
    });

    it('n\'offre pas de transfert pour une ligne déjà commandée chez le fournisseur', function () {
        stockAtStore($this->product, $this->gaspe, 1);
        $supplierOrder = $this->line->supplierOrderLine->order;
        $supplierOrder->markPending();
        $supplierOrder->send();

        expect(fn () => app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1, $this->line->fresh()))
            ->toThrow(DomainException::class, 'annulez d\'abord la ligne chez le fournisseur');

        Livewire::test(LineTransfer::class, ['lineId' => $this->line->id])->assertDontSee('Transférer d\'un autre magasin');
    });
});

describe('menu « Transferts »', function () {
    it('montre à chaque magasin ses transferts à accepter, demandés et en transit', function () {
        stockAtStore($this->product, $this->gaspe, 2);
        app(RequestStoreTransfer::class)->handle($this->salesman, $this->product, $this->gaspe->id, 1);

        Livewire::actingAs($this->clerkGaspe)->test(Transfers::class)
            ->assertViewHas('toAccept', fn ($transfers) => $transfers->count() === 1)
            ->assertSee('Accepter');

        Livewire::actingAs($this->salesman)->test(Transfers::class)
            ->assertViewHas('requested', fn ($transfers) => $transfers->count() === 1)
            ->assertViewHas('toAccept', fn ($transfers) => $transfers->isEmpty());
    });

    it('demande un transfert de stock depuis le menu', function () {
        stockAtStore($this->product, $this->gaspe, 3);

        Livewire::test(Transfers::class)
            ->call('openRequest')
            ->set('productSearch', 'SOFA')
            ->assertSee('SOFA-1')
            ->call('selectProduct', $this->product->id)
            ->assertSet('fromStoreId', (string) $this->gaspe->id)
            ->set('quantity', '2')
            ->call('requestTransfer')
            ->assertHasNoErrors();

        expect(StoreTransfer::sole()->quantity)->toBe(2);
    });

    it('refuse le menu sans permission de transfert, et l\'acceptation aux vendeurs', function () {
        $this->actingAs(User::factory()->withRole('accounting')->create())->get(route('inventory.transfers'))->assertForbidden();

        expect($this->salesman->can('transfers.request'))->toBeTrue()
            ->and($this->salesman->can('transfers.accept'))->toBeFalse()
            ->and($this->clerkGaspe->can('transfers.accept'))->toBeTrue()
            ->and($this->clerkGaspe->can('transfers.receive'))->toBeTrue()
            ->and(User::factory()->withRole('manager')->create()->can('transfers.request'))->toBeTrue();
    });
});
