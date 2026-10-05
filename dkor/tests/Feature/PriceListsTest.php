<?php

use App\Enums\SupplierType;
use App\Livewire\Catalog\PriceLists;
use App\Livewire\Catalog\PriceListShow;
use App\Livewire\Products\Show;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\ProductUpc;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-04 10:00:00');
    $this->actingAs(User::factory()->withRole('admin')->create());
});

afterEach(fn () => Carbon::setTestNow());

it('crée une liste avec la fin par défaut au 31 décembre dans 5 ans', function () {
    $supplier = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(PriceLists::class)
        ->call('openCreate')
        ->assertSet('startsOn', '2026-10-04')
        ->assertSet('endsOn', '2031-12-31')
        ->set('supplierId', (string) $supplier->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(PriceList::firstOrFail())->supplier_id->toBe($supplier->id);
});

it('refuse une liste dont la période chevauche une liste active du même fournisseur', function () {
    $list = PriceList::factory()->create();

    Livewire::test(PriceLists::class)
        ->call('openCreate')
        ->set('supplierId', (string) $list->supplier_id)
        ->call('save')
        ->assertHasErrors('supplierId');

    expect(PriceList::count())->toBe(1);
});

it('permet une nouvelle liste quand l\'ancienne est archivée', function () {
    $list = PriceList::factory()->archived()->create();

    Livewire::test(PriceLists::class)
        ->call('openCreate')
        ->set('supplierId', (string) $list->supplier_id)
        ->call('save')
        ->assertHasNoErrors();

    expect(PriceList::count())->toBe(2);
});

it('refuse une date de fin antérieure au début et un fournisseur non produit', function () {
    $service = Supplier::factory()->create(['type' => SupplierType::Service]);
    $product = Supplier::factory()->create(['type' => SupplierType::Product]);

    Livewire::test(PriceLists::class)
        ->call('openCreate')
        ->set('supplierId', (string) $service->id)
        ->call('save')
        ->assertHasErrors('supplierId')
        ->set('supplierId', (string) $product->id)
        ->set('endsOn', '2026-01-01')
        ->call('save')
        ->assertHasErrors('endsOn');
});

it('n\'affiche aucun fournisseur sans recherche ni les fournisseurs sans liste active', function () {
    PriceList::factory()->create();
    Supplier::factory()->create(['type' => SupplierType::Product, 'name' => 'Gamma Sans Liste']);

    Livewire::test(PriceLists::class)
        ->assertSee('Recherchez un fournisseur')
        ->set('search', 'Gamma')
        ->assertDontSeeHtml('catalog/price-lists/');
});

it('recherche un fournisseur et lie vers la liste active', function () {
    $list = PriceList::factory()->create();
    $list->supplier->update(['name' => 'Alpha Meubles']);
    $beta = PriceList::factory()->for(Supplier::factory()->create(['type' => SupplierType::Product, 'name' => 'Beta Déco']))->create();

    Livewire::test(PriceLists::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Meubles')
        ->assertDontSeeHtml('catalog/price-lists/'.$beta->id)
        ->assertSeeHtml('catalog/price-lists/'.$list->id)
        ->set('search', 'Gamma')
        ->assertSee('Aucune liste de prix active pour ce fournisseur.');
});

it('modifie les dates et archive une liste', function () {
    $list = PriceList::factory()->create();

    Livewire::test(PriceListShow::class, ['priceList' => $list])
        ->set('endsOn', '2030-06-30')
        ->call('save')
        ->assertHasNoErrors()
        ->call('archive');

    expect($list->fresh())->ends_on->toDateString()->toBe('2030-06-30')->archived_at->not->toBeNull();
});

it('archive automatiquement les listes expirées', function () {
    $expired = PriceList::factory()->create(['ends_on' => '2026-10-03']);
    $endsToday = PriceList::factory()->create(['ends_on' => '2026-10-04']);

    $this->artisan('price-lists:archive-expired')->assertSuccessful();

    expect($expired->fresh()->archived_at)->not->toBeNull()
        ->and($endsToday->fresh()->archived_at)->toBeNull();
});

it('planifie l\'archivage à 4 h', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('price-lists:archive-expired')->assertSuccessful();
});

it('refuse la création sans permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('catalog.price-lists'))->assertForbidden();
});

// ── Listes et importation CSV ──────────────────────────────────────────────

function csvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('liste.csv', $content);
}

it('ajoute une liste avec un nom et un escompte', function () {
    $priceList = PriceList::factory()->create();

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openAddList')
        ->set('listName', 'Printemps')
        ->set('listDiscount', '12.5')
        ->call('addList')
        ->assertHasNoErrors()
        ->assertSee('Printemps')
        ->assertSee('0 produit');

    expect(PriceListList::firstOrFail())->name->toBe('Printemps')->discount_percent->toBe(12.5);
});

it('refuse un escompte hors limites', function () {
    Livewire::test(PriceListShow::class, ['priceList' => PriceList::factory()->create()])
        ->call('openAddList')
        ->set('listName', 'X')
        ->set('listDiscount', '120')
        ->call('addList')
        ->assertHasErrors('listDiscount');
});

it('importe le CSV, conserve toutes les lignes et met à jour seulement les produits trouvés', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create(['discount_percent' => 10]);
    $other = Supplier::factory()->create(['type' => SupplierType::Product]);

    $match = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'ab 100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100', 'cost' => 5, 'description' => 'Ancienne']);
    $foreign = Product::factory()->create(['supplier_id' => $other->id, 'model' => 'ab 100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100', 'cost' => 5]);

    $csv = "\xEF\xBB\xBFModele;cout;IMAP;UPC;collection;description;longueur;largeur;hauteur;poids\n"
        ."AB 100;100,00;150;123456;Nordique;;10,5;20;30;4\n"
        ."ZZ-1;50;;;;Nouveau;;;;\n";

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload($csv))
        ->call('import')
        ->assertHasNoErrors();

    expect(PriceListItem::count())->toBe(2)
        ->and(PriceListItem::where('clean_model', 'ab100')->firstOrFail())
        ->product_id->toBe($match->id)->imap->toBe(150.0)->upc->toBe('123456')->cost->toBe(100.0)
        ->and(PriceListItem::where('clean_model', 'zz1')->firstOrFail())->product_id->toBeNull();

    expect($match->fresh())
        ->cost->toBe(90.0)
        ->collection->toBe('Nordique')
        ->description->toBe('Ancienne')
        ->length->toBe(10.5)
        ->weight->toBe(4.0)
        ->imap->toBe(150.0)
        ->and($foreign->fresh()->cost)->toBe(5.0);
});

it('importe par lots de 200 et remplace le contenu précédent', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    PriceListItem::factory()->for($list)->create();

    $csv = "Modele;cout\n".collect(range(1, 450))->map(fn ($i) => "M{$i};{$i}")->implode("\n");

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload($csv))
        ->call('import')
        ->assertHasNoErrors();

    expect($list->items()->count())->toBe(450);
});

it('refuse un CSV sans les colonnes requises', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload("Nom;prix\nA;1\n"))
        ->call('import')
        ->assertHasErrors('file');
});

it('refuse l\'ajout et l\'importation sans permission', function () {
    $this->actingAs(User::factory()->withRole('salesman')->create()->givePermissionTo('price_lists.view'));

    Livewire::test(PriceListShow::class, ['priceList' => PriceList::factory()->create()])
        ->call('openAddList')
        ->assertForbidden();
});

it('ajoute les UPC importés sans remplacer ni dupliquer les existants', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'ab 100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100']);
    ProductUpc::factory()->for($product)->create(['upc' => '111']);

    $component = Livewire::test(PriceListShow::class, ['priceList' => $priceList])->call('openImport', $list->id);

    $component->set('file', csvUpload("Modele;cout;UPC\nAB100;10;222\n"))->call('import');
    $component->call('openImport', $list->id)->set('file', csvUpload("Modele;cout;UPC\nAB100;10;222\nAB100;10;111\n"))->call('import');

    expect($product->upcs()->orderBy('id')->pluck('upc')->all())->toBe(['111', '222']);
});

it('ignore les lignes dont le coût est vide, nul ou invalide', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'ab 100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100', 'cost' => 5]);

    $csv = "Modele;cout\nAB100;0\nA2;0,00\nA3;\nA4;abc\nA5;-3\nA6;12,50\n";

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload($csv))
        ->call('import');

    expect($list->items()->pluck('model')->all())->toBe(['A6'])
        ->and($product->fresh()->cost)->toBe(5.0);
});

it('vide un UPC non numérique sans bloquer l\'importation', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'ab 100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100']);

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload("Modele;cout;UPC\nAB100;10;N/A\nA2;5;0123456789\nA3;5;1e5\nA4;5;12,5\nA5;5;12.5\nA6;5;-12\nA7;5;12 34\n"))
        ->call('import')
        ->assertHasNoErrors();

    expect($list->items()->orderBy('id')->pluck('upc')->all())->toBe([null, '0123456789', null, null, null, null, null])
        ->and($product->upcs()->count())->toBe(0);
});

it('n\'accepte que des chiffres pour un UPC ajouté à la main', function () {
    $product = Product::factory()->create(['clean_model' => 'abc'])->fresh();

    Livewire::test(Show::class, ['product' => $product])
        ->set('newUpc', '12.5')
        ->call('addUpc')
        ->assertHasErrors('newUpc')
        ->set('newUpc', ' 0123456 ')
        ->call('addUpc')
        ->assertHasNoErrors()
        ->set('newUpc', '0123456')
        ->call('addUpc');

    expect($product->upcs()->pluck('upc')->all())->toBe(['0123456']);
});

it('apparie sur le modèle fournisseur nettoyé, qui reprend le modèle par défaut', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $withSupplierModel = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'Chaise X', 'clean_model' => 'chaisex', 'supplier_model' => 'CX-1', 'cost' => 5]);
    $withoutSupplierModel = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'Table T', 'clean_model' => 'tablet', 'supplier_model' => null, 'cost' => 5]);

    expect($withSupplierModel->supplier_clean_model)->toBe('cx1');

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload("Modele;cout\nCX 1;10\nChaise X;20\nTABLE-T;30\n"))
        ->call('import');

    expect($withSupplierModel->fresh()->cost)->toBe(10.0)
        ->and($withoutSupplierModel->fresh()->cost)->toBe(30.0);
});

it('exige que le modèle fournisseur nettoyé soit identique au modèle nettoyé et unique', function () {
    $product = Product::factory()->create(['model' => 'Chaise X', 'clean_model' => 'chaisex', 'cost' => 5])->fresh();
    Product::factory()->create(['supplier_id' => $product->supplier_id, 'model' => 'Autre', 'clean_model' => 'autre', 'supplier_model' => 'ZZ-9']);

    Livewire::test(Show::class, ['product' => $product])
        ->set('supplierModel', 'CX-1')
        ->call('saveGeneral')
        ->assertHasErrors('supplierModel')
        ->set('supplierModel', 'Chaise-X')
        ->call('saveGeneral')
        ->assertHasNoErrors();

    expect($product->fresh()->supplier_clean_model)->toBe('chaisex');
});

it('verrouille le modèle fournisseur d\'un produit présent dans une liste de prix', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'Chaise X', 'clean_model' => 'chaisex', 'supplier_model' => 'Chaise X', 'cost' => 5])->fresh();

    $component = Livewire::test(Show::class, ['product' => $product])
        ->set('supplierModel', 'Chaise-X')
        ->call('saveGeneral')
        ->assertHasNoErrors();

    PriceListItem::factory()->for($list)->create(['model' => 'Chaise X', 'clean_model' => 'chaisex']);

    $component->set('supplierModel', 'CHAISE X')
        ->call('saveGeneral')
        ->assertHasErrors('supplierModel');

    expect($product->fresh()->supplier_model)->toBe('Chaise-X');
});

it('exige un modèle fournisseur et le reprend du modèle à la création', function () {
    $product = Product::factory()->create(['model' => 'Lampe L', 'clean_model' => 'lampel', 'supplier_model' => null]);

    expect($product->supplier_model)->toBe('Lampe L')
        ->and($product->supplier_clean_model)->toBe('lampel');

    Livewire::test(Show::class, ['product' => $product->fresh()])
        ->set('supplierModel', '')
        ->call('saveGeneral')
        ->assertHasErrors('supplierModel');
});

it('ignore à l\'importation les modèles déjà présents dans une autre liste de la même liste de prix', function () {
    $priceList = PriceList::factory()->create();
    $first = PriceListList::factory()->for($priceList)->create();
    $second = PriceListList::factory()->for($priceList)->create();
    $otherPriceList = PriceListList::factory()->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100', 'cost' => 5]);

    $component = Livewire::test(PriceListShow::class, ['priceList' => $priceList]);
    $component->call('openImport', $first->id)->set('file', csvUpload("Modele;cout\nAB-100;10\nCD-1;5\n"))->call('import');
    Product::whereKey($product->id)->update(['cost' => 5]);
    $component->call('openImport', $second->id)->set('file', csvUpload("Modele;cout\nab 100;99\nCD-2;7\nCD-2;8\n"))->call('import');

    expect($first->items()->count())->toBe(2)
        ->and($second->items()->pluck('model')->all())->toBe(['CD-2'])
        ->and($product->fresh()->cost)->toBe(5.0);

    Livewire::test(PriceListShow::class, ['priceList' => $otherPriceList->priceList])
        ->call('openImport', $otherPriceList->id)
        ->set('file', csvUpload("Modele;cout\nAB-100;10\n"))
        ->call('import');

    expect($otherPriceList->items()->count())->toBe(1);
});

it('supprime une liste avec ses produits importés', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    PriceListItem::factory()->for($list)->create();
    $kept = PriceListList::factory()->for($priceList)->create();

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])->call('deleteList', $list->id);

    expect(PriceListList::pluck('id')->all())->toBe([$kept->id])->and(PriceListItem::count())->toBe(0);
});

it('refuse de supprimer une liste sans permission ou d\'une autre liste de prix', function () {
    $list = PriceListList::factory()->create();

    Livewire::test(PriceListShow::class, ['priceList' => PriceList::factory()->create()])
        ->call('deleteList', $list->id)
        ->assertNotFound();

    $this->actingAs(User::factory()->withRole('salesman')->create()->givePermissionTo('price_lists.view'));

    Livewire::test(PriceListShow::class, ['priceList' => $list->priceList])
        ->call('deleteList', $list->id)
        ->assertForbidden();
});

it('permet une liste future qui ne chevauche pas la liste active du fournisseur', function () {
    $current = PriceList::factory()->create(['ends_on' => '2027-12-31']);

    Livewire::test(PriceLists::class)
        ->assertSee('Ajouter une liste')
        ->call('openCreate')
        ->assertSeeHtml('value="'.$current->supplier_id.'"')
        ->set('supplierId', (string) $current->supplier_id)
        ->set('startsOn', '2027-06-01')
        ->call('save')
        ->assertHasErrors('supplierId')
        ->set('startsOn', '2028-01-01')
        ->set('endsOn', '2032-12-31')
        ->call('save')
        ->assertHasNoErrors();

    expect(PriceList::where('supplier_id', $current->supplier_id)->count())->toBe(2);
});

it('affiche une liste future avec le badge à venir', function () {
    $list = PriceList::factory()->create(['starts_on' => '2027-01-01']);

    Livewire::test(PriceLists::class)->set('search', $list->supplier->name)->assertSee('À venir');
});

it('refuse de modifier les dates pour chevaucher une autre liste active', function () {
    $current = PriceList::factory()->create(['ends_on' => '2027-12-31']);
    $future = PriceList::factory()->create(['supplier_id' => $current->supplier_id, 'starts_on' => '2028-01-01', 'ends_on' => '2030-12-31']);

    Livewire::test(PriceListShow::class, ['priceList' => $future])
        ->set('startsOn', '2027-06-01')
        ->call('save')
        ->assertHasErrors('startsOn')
        ->set('startsOn', '2028-03-01')
        ->call('save')
        ->assertHasNoErrors();
});

it('n\'applique pas aux produits une liste future et l\'applique à sa date de début', function () {
    $priceList = PriceList::factory()->create(['starts_on' => '2026-11-01']);
    $list = PriceListList::factory()->for($priceList)->create(['discount_percent' => 10]);
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100', 'supplier_model' => 'AB-100', 'cost' => 5]);

    Livewire::test(PriceListShow::class, ['priceList' => $priceList])
        ->call('openImport', $list->id)
        ->set('file', csvUpload("Modele;cout;UPC\nAB100;100;555\n"))
        ->call('import')
        ->assertHasNoErrors();

    $this->artisan('price-lists:apply')->assertSuccessful();

    expect($product->fresh()->cost)->toBe(5.0)
        ->and($list->items()->count())->toBe(1)
        ->and($list->fresh()->applied_at)->toBeNull();

    Carbon::setTestNow('2026-11-01 05:00:00');
    $this->artisan('price-lists:apply')->assertSuccessful();

    expect($product->fresh()->cost)->toBe(90.0)
        ->and($product->upcs()->pluck('upc')->all())->toBe(['555'])
        ->and($list->items()->firstOrFail()->product_id)->toBe($product->id)
        ->and($list->fresh()->applied_at)->not->toBeNull();

    $product->update(['cost' => 7]);
    $this->artisan('price-lists:apply')->assertSuccessful();

    expect($product->fresh()->cost)->toBe(7.0);
});

it('planifie l\'application des listes à 5 h', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('price-lists:apply')->assertSuccessful();
});

it('verrouille la date de début d\'une liste en cours mais permet de changer la fin', function () {
    $list = PriceList::factory()->create(['starts_on' => '2026-10-04', 'ends_on' => '2030-12-31']);

    Livewire::test(PriceListShow::class, ['priceList' => $list])
        ->assertSee('date de début est verrouillée')
        ->set('startsOn', '2026-12-01')
        ->set('endsOn', '2031-06-30')
        ->call('save')
        ->assertHasNoErrors();

    expect($list->fresh())->starts_on->toDateString()->toBe('2026-10-04')->ends_on->toDateString()->toBe('2031-06-30');
});

it('permet de changer la date de début d\'une liste future', function () {
    $list = PriceList::factory()->create(['starts_on' => '2027-01-01']);

    Livewire::test(PriceListShow::class, ['priceList' => $list])
        ->assertDontSee('date de début est verrouillée')
        ->set('startsOn', '2027-03-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($list->fresh()->starts_on->toDateString())->toBe('2027-03-01');
});
