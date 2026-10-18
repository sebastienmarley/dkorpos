<?php

use App\Livewire\Catalog\Departments;
use App\Livewire\Catalog\Styles;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Show as ProductShow;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Role;
use App\Models\Style;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

describe('départements et catégories', function () {
    it('crée une catégorie dans le département choisi', function () {
        $department = Department::factory()->create();

        Livewire::test(Departments::class)
            ->call('openCreateCategory', $department->id)
            ->assertSet('categoryDepartmentId', (string) $department->id)
            ->set('categoryName', 'Tabourets')
            ->call('saveCategory')
            ->assertHasNoErrors();

        expect(Category::sole())->department_id->toBe($department->id)->name->toBe('Tabourets');
    });

    it('exige un département pour une catégorie', function () {
        Livewire::test(Departments::class)
            ->call('openCreateCategory')
            ->set('categoryName', 'Tabourets')
            ->call('saveCategory')
            ->assertHasErrors(['categoryDepartmentId' => 'required']);
    });

    it('refuse une catégorie en double dans un même département seulement', function () {
        $existing = Category::factory()->create(['name' => 'Tabourets']);

        Livewire::test(Departments::class)
            ->call('openCreateCategory', $existing->department_id)
            ->set('categoryName', 'Tabourets')
            ->call('saveCategory')
            ->assertHasErrors(['categoryName' => 'unique'])
            ->set('categoryDepartmentId', (string) Department::factory()->create()->id)
            ->call('saveCategory')
            ->assertHasNoErrors();
    });

    it('n\'affiche que l\'onglet couleurs avec la seule permission des couleurs', function () {
        Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
        $user = User::factory()->withRole('visiteur')->create();
        $user->givePermissionTo('colors.view');

        $this->actingAs($user)
            ->get(route('catalog.classification', ['tab' => 'departments']))
            ->assertOk()
            ->assertSee('Couleurs offertes pour les produits.')
            ->assertDontSee('Départements et catégories');
    });
});

describe('styles', function () {
    it('crée un style et refuse un nom en double', function () {
        Livewire::test(Styles::class)
            ->call('openCreate')
            ->set('name', 'Scandinave')
            ->call('save')
            ->assertHasNoErrors()
            ->call('openCreate')
            ->set('name', 'Scandinave')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);

        expect(Style::sole()->name)->toBe('Scandinave');
    });

    it('enregistre le style d\'un produit depuis sa fiche', function () {
        $style = Style::factory()->create();
        $product = Product::factory()->create(['clean_model' => 'abc'])->fresh();

        Livewire::test(ProductShow::class, ['product' => $product])
            ->set('styleId', (string) $style->id)
            ->call('saveDescription')
            ->assertHasNoErrors();

        expect($product->fresh()->style_id)->toBe($style->id);
    });
});

describe('classification d\'un produit', function () {
    it('enregistre la catégorie et en déduit le département', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['model' => 'ABC-1', 'clean_model' => 'ABC1', 'supplier_model' => 'ABC-1'])->fresh();

        Livewire::test(ProductShow::class, ['product' => $product])
            ->set('departmentId', (string) $category->department_id)
            ->set('categoryId', (string) $category->id)
            ->call('saveGeneral')
            ->assertHasNoErrors();

        expect($product->fresh()->department->id)->toBe($category->department_id);
    });

    it('retire la catégorie quand le département change', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'clean_model' => 'abc'])->fresh();

        Livewire::test(ProductShow::class, ['product' => $product])
            ->assertSet('departmentId', (string) $category->department_id)
            ->set('departmentId', (string) Department::factory()->create()->id)
            ->assertSet('categoryId', '');
    });

    it('refuse une catégorie d\'un autre département', function () {
        $product = Product::factory()->create(['model' => 'ABC-1', 'clean_model' => 'ABC1', 'supplier_model' => 'ABC-1'])->fresh();

        Livewire::test(ProductShow::class, ['product' => $product])
            ->set('departmentId', (string) Department::factory()->create()->id)
            ->set('categoryId', (string) Category::factory()->create()->id)
            ->call('saveGeneral')
            ->assertHasErrors('categoryId');
    });

    it('filtre la liste des produits par département puis par catégorie', function () {
        $stools = Category::factory()->create();
        $tables = Category::factory()->create(['department_id' => $stools->department_id]);
        $stool = Product::factory()->create(['category_id' => $stools->id]);
        $table = Product::factory()->create(['category_id' => $tables->id]);
        Product::factory()->create();

        $component = Livewire::test(ProductsIndex::class)->set('departmentId', (string) $stools->department_id);
        expect($component->instance()->getProducts()->pluck('id')->all())->toEqualCanonicalizing([$stool->id, $table->id]);

        $component->set('categoryId', (string) $stools->id);
        expect($component->instance()->getProducts()->pluck('id')->all())->toBe([$stool->id]);
    });
});
