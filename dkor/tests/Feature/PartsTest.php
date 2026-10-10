<?php

use App\Enums\CustomerOrderLineStatus;
use App\Enums\SupplierType;
use App\Livewire\PartPicker;
use App\Livewire\Parts\Index;
use App\Livewire\Parts\Show;
use App\Livewire\Products\Show as ProductShow;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
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

it('calcule le prix de vente à partir du dernier coût et du multiplicateur du fournisseur', function (float $lastCost, float $expected) {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'base_multiplier' => 2.5]);

    expect(Part::factory()->create(['supplier_id' => $supplier->id, 'last_cost' => $lastCost])->selling_price)->toBe($expected);
})->with([
    'sous 20 $, arrondi à x,99' => [3, 7.99],
    'entre 20 et 100 $, arrondi au dollar' => [10, 25.0],
    'pièce qui ne coûte rien' => [0, 0.0],
]);

it('affiche le prix de vente calculé dans le catalogue et sur la fiche', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product, 'base_multiplier' => 2.5]);
    $part = Part::factory()->create(['supplier_id' => $supplier->id, 'last_cost' => 3]);

    Livewire::test(Index::class)->assertSee('7.99 $');
    Livewire::test(Show::class, ['part' => $part])->assertSee('7.99 $');
});

/**
 * Pièce ajoutée à une nouvelle commande client (commandée sur le brouillon de son fournisseur).
 *
 * @param  array<string, mixed>  $part
 * @param  array<string, mixed>  $customer
 * @param  array<string, mixed>  $order
 */
function orderedPartLine(array $part = [], array $customer = [], array $order = []): CustomerOrderLine
{
    $customerOrder = CustomerOrder::factory()->create(['customer_id' => customer::factory()->create($customer)->id, ...$order]);

    return $customerOrder->addPart(Part::factory()->create($part))->fresh();
}

describe('onglet Commandes fournisseur', function () {
    it('affiche chaque pièce commandée avec son fournisseur, sa commande fournisseur, son client et son statut', function () {
        $line = orderedPartLine(['model' => 'PC-12'], ['firstname' => 'Julie', 'lastname' => 'Tremblay']);

        Livewire::test(Index::class)
            ->set('tab', 'orders')
            ->assertSee('PC-12')
            ->assertSee($line->part->supplier->name)
            ->assertSee($line->supplierOrderLine->order->number)
            ->assertSee('Julie Tremblay')
            ->assertSee(__('Commande client #:id', ['id' => $line->customer_order_id]))
            ->assertSee(CustomerOrderLineStatus::OnOrder->label());
    });

    it('filtre par étape du suivi', function (string $stage, string $expected) {
        orderedPartLine(['model' => 'PC-COMMANDEE'])->update(['status' => CustomerOrderLineStatus::Ordered]);
        orderedPartLine(['model' => 'PC-RECUE'])->update(['status' => CustomerOrderLineStatus::Received]);
        orderedPartLine(['model' => 'PC-REMISE'])->update(['status' => CustomerOrderLineStatus::PickedUp]);

        $component = Livewire::test(Index::class)
            ->set('tab', 'orders')
            ->set('orderStage', $stage)
            ->assertSee($expected);

        foreach (array_diff(['PC-COMMANDEE', 'PC-RECUE', 'PC-REMISE'], [$expected]) as $other) {
            $component->assertDontSee($other);
        }
    })->with([
        'commandée' => ['ordered', 'PC-COMMANDEE'],
        'reçue — à remettre' => ['received', 'PC-RECUE'],
        'remise' => ['handed_over', 'PC-REMISE'],
    ]);

    it('cherche par modèle, description, client ou numéro de commande', function (string $term) {
        $line = orderedPartLine(['model' => 'PC-12', 'description' => 'Verre de lampe'], ['firstname' => 'Julie', 'lastname' => 'Tremblay'], ['id' => 4242]);
        $line->supplierOrderLine->order->update(['number' => 'CF-777777']);
        orderedPartLine(['model' => 'ZX-99', 'description' => 'Pied de chaise'], ['firstname' => 'Marc', 'lastname' => 'Gagnon'], ['id' => 5151]);

        Livewire::test(Index::class)
            ->set('tab', 'orders')
            ->set('orderSearch', $term)
            ->assertSee('PC-12')
            ->assertDontSee('ZX-99');
    })->with([
        'modèle' => 'pc 12',
        'description' => 'verre',
        'client' => 'tremblay',
        'numéro de commande client' => '4242',
        'numéro de commande fournisseur' => '777777',
    ]);

    it('mène aux pages de la commande fournisseur et de la commande client', function () {
        $line = orderedPartLine();

        Livewire::test(Index::class)
            ->set('tab', 'orders')
            ->assertSee(route('supplier-orders.show', $line->supplierOrderLine->order))
            ->assertSee(route('customer-orders.show', $line->order));
    });

    it('affiche la commande fournisseur sans lien quand l\'usager ne peut pas la consulter', function () {
        $line = orderedPartLine();
        $this->actingAs(User::factory()->withRole('salesman')->create());

        Livewire::test(Index::class)
            ->set('tab', 'orders')
            ->assertSee($line->supplierOrderLine->order->number)
            ->assertDontSee(route('supplier-orders.show', $line->supplierOrderLine->order));
    });
});
