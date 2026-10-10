<?php

use App\Enums\SupplierType;
use App\Livewire\PartPicker;
use App\Livewire\Parts\Index;
use App\Livewire\Parts\Show;
use App\Livewire\Products\Show as ProductShow;
use App\Models\Part;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

it('cherche les pièces par modèle nettoyé, description ou produit lié', function (string $term) {
    $lampGlass = Part::factory()->create(['model' => 'PC-12', 'description' => 'Verre de lampe']);
    $lampGlass->products()->attach(Product::factory()->create(['model' => 'LAMPE-ARC', 'clean_model' => 'lampearc']));
    Part::factory()->create(['model' => 'ZX-99', 'description' => 'Pied de chaise']);

    Livewire::test(Index::class)
        ->set('search', $term)
        ->assertSee('PC-12')
        ->assertDontSee('ZX-99');
})->with([
    'modèle nettoyé' => 'pc 12',
    'description' => 'verre',
    'produit lié' => 'LAMPE-ARC',
]);

it('propose les pièces existantes dans « Ajouter une pièce » à partir de 2 caractères', function () {
    Part::factory()->create(['model' => 'PC-12', 'description' => 'Verre de lampe']);

    Livewire::test(PartPicker::class)
        ->call('open')
        ->set('search', 'p')
        ->assertDontSee('PC-12')
        ->set('search', 'pc')
        ->assertSee('PC-12');
});

it('choisit une pièce existante et la transmet à la page', function () {
    $part = Part::factory()->create();

    Livewire::test(PartPicker::class)
        ->call('open')
        ->call('select', $part->id)
        ->assertSet('showModal', false)
        ->assertDispatched('part-selected', id: $part->id);
});

it('crée une pièce et la transmet à la page', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(PartPicker::class)
        ->call('open')
        ->set('search', 'PC-12')
        ->call('startCreating')
        ->assertSet('model', 'PC-12')
        ->set('supplierId', (string) $supplier->id)
        ->set('description', 'Verre de lampe')
        ->set('lastCost', '4,50')
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('part-selected', id: Part::sole()->id);

    expect(Part::sole())
        ->supplier_id->toBe($supplier->id)
        ->clean_model->toBe('pc12')
        ->description->toBe('Verre de lampe')
        ->last_cost->toBe(4.5);
});

it('sélectionne la pièce existante du fournisseur au lieu de créer un doublon', function () {
    $existing = Part::factory()->create(['model' => 'PC-12', 'description' => 'Verre de lampe']);

    Livewire::test(PartPicker::class)
        ->call('open')
        ->call('startCreating')
        ->set('supplierId', (string) $existing->supplier_id)
        ->set('model', 'pc 12')
        ->set('description', 'Autre description')
        ->call('create')
        ->assertDispatched('part-selected', id: $existing->id);

    expect(Part::count())->toBe(1)
        ->and($existing->fresh()->description)->toBe('Verre de lampe');
});

it('crée une pièce distincte pour le même modèle chez un autre fournisseur', function () {
    Part::factory()->create(['model' => 'PC-12']);
    $otherSupplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(PartPicker::class)
        ->call('open')
        ->call('startCreating')
        ->set('supplierId', (string) $otherSupplier->id)
        ->set('model', 'PC-12')
        ->set('description', 'Verre de lampe')
        ->call('create');

    expect(Part::count())->toBe(2);
});

it('exige le fournisseur, le modèle et la description', function () {
    Livewire::test(PartPicker::class)
        ->call('open')
        ->call('startCreating')
        ->call('create')
        ->assertHasErrors(['supplierId' => 'required', 'model' => 'required', 'description' => 'required'])
        ->assertNotDispatched('part-selected');

    expect(Part::count())->toBe(0);
});

it('ouvre la fiche de la pièce choisie depuis la page des pièces', function () {
    $part = Part::factory()->create();

    Livewire::test(Index::class)
        ->dispatch('part-selected', id: $part->id)
        ->assertRedirect(route('parts.show', $part));
});

it('lie une pièce à des produits, visibles des deux côtés, puis retire un lien', function () {
    $part = Part::factory()->create(['model' => 'PC-12']);
    $lamp = Product::factory()->create(['model' => 'LAMPE-ARC', 'clean_model' => 'lampearc']);
    $otherLamp = Product::factory()->create(['model' => 'LAMPE-BOL', 'clean_model' => 'lampebol']);

    Livewire::test(Show::class, ['part' => $part])
        ->set('productSearch', 'LAMPE')
        ->call('attachProduct', $lamp->id)
        ->call('attachProduct', $otherLamp->id)
        ->assertSee('LAMPE-ARC')
        ->assertSee('LAMPE-BOL')
        ->call('detachProduct', $otherLamp->id);

    expect($part->products()->pluck('products.id')->all())->toBe([$lamp->id]);
    Livewire::test(ProductShow::class, ['product' => $lamp->fresh()])->assertSee('PC-12');
    Livewire::test(ProductShow::class, ['product' => $otherLamp->fresh()])->assertDontSee('PC-12');
});

it('refuse de renommer une pièce avec le modèle d\'une autre pièce du fournisseur', function () {
    $existing = Part::factory()->create(['model' => 'PC-12']);
    $part = Part::factory()->create(['supplier_id' => $existing->supplier_id, 'model' => 'PC-13']);

    Livewire::test(Show::class, ['part' => $part])
        ->set('model', 'pc 12')
        ->call('save')
        ->assertHasErrors('model');

    expect($part->fresh()->model)->toBe('PC-13');
});

it('modifie la fiche d\'une pièce', function () {
    $part = Part::factory()->create(['model' => 'PC-12']);

    Livewire::test(Show::class, ['part' => $part])
        ->set('model', 'PC-12B')
        ->set('description', 'Verre givré')
        ->set('lastCost', '7.25')
        ->call('save')
        ->assertHasNoErrors();

    expect($part->fresh())
        ->model->toBe('PC-12B')
        ->clean_model->toBe('pc12b')
        ->description->toBe('Verre givré')
        ->last_cost->toBe(7.25);
});
