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

it('refuse une deuxième liste active pour le même fournisseur', function () {
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
    PriceList::factory()->for(Supplier::factory()->create(['type' => SupplierType::Product, 'name' => 'Beta Déco']))->create();

    Livewire::test(PriceLists::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Meubles')
        ->assertDontSee('Beta Déco')
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

    $match = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100', 'cost' => 5, 'description' => 'Ancienne']);
    $foreign = Product::factory()->create(['supplier_id' => $other->id, 'model' => 'AB-100', 'clean_model' => 'ab100', 'cost' => 5]);

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
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100']);
    ProductUpc::factory()->for($product)->create(['upc' => '111']);

    $component = Livewire::test(PriceListShow::class, ['priceList' => $priceList])->call('openImport', $list->id);

    $component->set('file', csvUpload("Modele;cout;UPC\nAB100;10;222\n"))->call('import');
    $component->call('openImport', $list->id)->set('file', csvUpload("Modele;cout;UPC\nAB100;10;222\nAB100;10;111\n"))->call('import');

    expect($product->upcs()->orderBy('id')->pluck('upc')->all())->toBe(['111', '222']);
});

it('ignore les lignes dont le coût est vide, nul ou invalide', function () {
    $priceList = PriceList::factory()->create();
    $list = PriceListList::factory()->for($priceList)->create();
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100', 'cost' => 5]);

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
    $product = Product::factory()->create(['supplier_id' => $priceList->supplier_id, 'model' => 'AB-100', 'clean_model' => 'ab100']);

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
