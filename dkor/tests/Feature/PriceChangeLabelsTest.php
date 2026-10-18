<?php

use App\Enums\LabelReason;
use App\Livewire\Labels\Index as LabelsIndex;
use App\Models\InventoryStock;
use App\Models\LabelRequest;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Livewire\Livewire;

/**
 * Produit en démonstration dont l'étiquette affiche déjà le prix de vente actuel.
 */
function labeledDemo(int $inDemo = 2): Product
{
    $product = Product::factory()->create(['cost' => Money::parse(500)]);
    $product->update(['displayed_price' => $product->selling_price]);
    InventoryStock::query()->create(['product_id' => $product->id, 'quantity_in_demo' => $inDemo]);

    return $product->fresh();
}

function automaticRequest(Product $product): ?LabelRequest
{
    return LabelRequest::query()->pending()->where('product_id', $product->id)->whereIn('reason', LabelReason::automatic())->first();
}

it('demande une étiquette par unité quand un produit sans étiquette arrive en démo', function () {
    $product = Product::factory()->create();

    InventoryStock::query()->create(['product_id' => $product->id, 'quantity_in_demo' => 3]);

    expect(automaticRequest($product))->reason->toBe(LabelReason::NewDemo)->quantity->toBe(3);
});

it('ne demande rien pour un démonstrateur dont l\'étiquette est à jour', function () {
    expect(automaticRequest(labeledDemo()))->toBeNull();
});

it('demande les étiquettes d\'un démonstrateur dont le coût change', function () {
    $product = labeledDemo(inDemo: 2);

    $product->update(['cost' => Money::parse(800)]);

    expect(automaticRequest($product))->reason->toBe(LabelReason::PriceChange)->quantity->toBe(2);
});

it('ignore un changement de prix d\'un produit qui n\'est pas en démo', function () {
    $product = Product::factory()->create();

    $product->update(['cost' => Money::parse(800)]);

    expect(LabelRequest::query()->pending()->count())->toBe(0);
});

it('demande les étiquettes quand le multiplicateur du fournisseur change', function () {
    $product = labeledDemo();

    $product->supplier->update(['base_multiplier' => (float) $product->supplier->base_multiplier + 0.5]);

    expect(automaticRequest($product))->reason->toBe(LabelReason::PriceChange);
});

it('retire la demande automatique quand le prix revient au prix affiché, sans toucher aux demandes manuelles', function () {
    $product = labeledDemo();
    LabelRequest::enqueue($product, 1);

    $product->update(['cost' => Money::parse(800)]);
    $product->update(['cost' => Money::parse(500)]);

    expect(automaticRequest($product))->toBeNull()
        ->and(LabelRequest::query()->pending()->sole()->reason)->toBe(LabelReason::Manual);
});

it('retient le prix imprimé à la confirmation et redemande au prochain changement', function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
    $product = labeledDemo();
    $product->update(['cost' => Money::parse(800)]);
    $request = automaticRequest($product);

    Livewire::test(LabelsIndex::class)
        ->set('selected', [$request->id])
        ->call('print')
        ->call('askConfirmation')
        ->call('confirmPrinted');

    $product->refresh();
    expect($product->displayed_price->equals($product->selling_price))->toBeTrue()
        ->and(automaticRequest($product))->toBeNull();

    $product->update(['cost' => Money::parse(900)]);

    expect(automaticRequest($product))->reason->toBe(LabelReason::PriceChange);
});

it('rattrape chaque jour les changements de prix non détectés', function () {
    $product = labeledDemo();
    $product->cost = Money::parse(800);
    $product->saveQuietly();

    $this->artisan('labels:queue-price-changes')->assertSuccessful();

    expect(automaticRequest($product))->reason->toBe(LabelReason::PriceChange);
});
