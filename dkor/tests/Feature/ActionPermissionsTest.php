<?php

use App\Livewire\Accounting\Currencies;
use App\Livewire\Accounting\Invoices\Create as InvoiceCreate;
use App\Livewire\Accounting\Invoices\Form as InvoiceForm;
use App\Livewire\Catalog\Categories;
use App\Livewire\Catalog\Colors;
use App\Livewire\Catalog\Departments;
use App\Livewire\CustomerForm;
use App\Livewire\Inventory\Movements as InventoryMovements;
use App\Livewire\Orders\Index as SupplierOrdersIndex;
use App\Livewire\Orders\Show as SupplierOrderShow;
use App\Livewire\ProductForm;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Receptions\Create as ReceptionCreate;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\Holidays;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\Schedules\Templates;
use App\Livewire\SupplierForm;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use Livewire\Livewire;

/**
 * Un usager qui peut voir toutes les pages mais n'a aucune permission d'action.
 */
function usagerLectureSeule(): User
{
    $role = Role::create(['name' => 'lecture_seule', 'label' => 'Lecture seule', 'level' => 0, 'guard_name' => 'web']);
    $role->givePermissionTo(collect(config('access.page_access'))->filter(fn (string $n) => str_ends_with($n, '.view'))->all());

    return User::factory()->withRole('lecture_seule')->create();
}

dataset('actions interdites', [
    'customers.create' => [fn () => Livewire::test(CustomerForm::class)->call('openCreate')],
    'customers.edit' => [fn () => Livewire::test(CustomerForm::class)->call('openEdit', 1)],
    'suppliers.create' => [fn () => Livewire::test(SupplierForm::class)->call('openCreate')],
    'currencies.create' => [fn () => Livewire::test(Currencies::class)->call('openCreate')],
    'currencies.archive' => [fn () => Livewire::test(Currencies::class)->call('toggleArchive', Currency::factory()->create()->id)],
    'supplier_orders.create' => [fn () => Livewire::test(SupplierOrdersIndex::class)->call('openCreate')],
    'supplier_orders.edit' => [fn () => Livewire::test(SupplierOrderShow::class, ['order' => SupplierOrder::factory()->create()])->call('send')],
    'supplier_orders.edit (annulation de ligne)' => [fn () => Livewire::test(SupplierOrderShow::class, ['order' => SupplierOrder::factory()->create()])->call('confirmLineCancellation', 1)],
    'supplier_orders.delete' => [fn () => Livewire::test(SupplierOrderShow::class, ['order' => SupplierOrder::factory()->create()])->call('deleteOrder')],
    'supplier_orders.edit (substitution)' => [fn () => Livewire::test(SupplierOrderShow::class, ['order' => SupplierOrder::factory()->create()])->call('openSubstitute', 1)],
    'receptions.create' => [fn () => Livewire::test(ReceptionCreate::class)->call('start')],
    'receptions.reverse' => [fn () => Livewire::test(SupplierOrderShow::class, ['order' => SupplierOrder::factory()->create()])->call('openReverse', 1)],
    'inventory.move' => [fn () => Livewire::test(InventoryMovements::class)->call('openMove')],
    'invoices.create' => [fn () => Livewire::test(InvoiceForm::class, ['reception' => Reception::factory()->create()])->call('save')],
    'invoices.create (facture libre)' => [fn () => Livewire::test(InvoiceCreate::class)->call('save')],
    'products.create' => [fn () => Livewire::test(ProductForm::class)->call('openCreate')],
    'departments.create' => [fn () => Livewire::test(Departments::class)->call('openCreate')],
    'categories.create' => [fn () => Livewire::test(Categories::class)->call('openCreate')],
    'colors.create' => [fn () => Livewire::test(Colors::class)->call('openCreate')],
    'schedule_management.publish' => [fn () => Livewire::test(ScheduleEdit::class)->call('publishWeek')],
    'schedule_management.edit' => [fn () => Livewire::test(ScheduleEdit::class)->call('copyPreviousWeek')],
    'schedule_templates.create' => [fn () => Livewire::test(Templates::class)->call('openCreateShift')],
    'schedule_templates.delete' => [fn () => Livewire::test(Templates::class)->call('deleteShift', 1)],
    'holidays.create' => [fn () => Livewire::test(Holidays::class)->call('openCreate')],
    'holidays.delete' => [fn () => Livewire::test(Holidays::class)->call('delete', 1)],
    'appointments.delete' => [fn () => Livewire::test(Appointments::class)->call('deleteAppointment')],
]);

it('refuse l\'action sans la permission', function (Closure $action) {
    $this->actingAs(usagerLectureSeule());

    $action()->assertForbidden();
})->with('actions interdites');

it('refuse la modification d\'un fournisseur et d\'un produit sans permission', function () {
    $this->actingAs(usagerLectureSeule());

    Livewire::test(SupplierShow::class, ['supplier' => Supplier::factory()->create()])
        ->call('saveIdentification')
        ->assertForbidden();

    Livewire::test(ProductShow::class, ['product' => Product::factory()->create(['clean_model' => 'abc'])->fresh()])
        ->call('saveGeneral')
        ->assertForbidden();
});

it('autorise l\'action avec la permission directe', function () {
    $user = usagerLectureSeule();
    $user->givePermissionTo('customers.create');

    $this->actingAs($user);

    Livewire::test(CustomerForm::class)->call('openCreate')->assertSet('showModal', true);
});

it('accorde les permissions d\'actions aux rôles par défaut', function (string $permission) {
    expect(User::factory()->create()->can($permission))->toBeTrue();
})->with(['customers.create', 'suppliers.edit', 'products.create', 'colors.edit', 'schedule_management.publish', 'holidays.delete', 'appointments.create']);

it('masque les boutons d\'ajout sans la permission', function () {
    $this->actingAs(usagerLectureSeule());

    $this->get(route('customers.index'))->assertOk()->assertDontSee('Ajouter un client');
    $this->get(route('catalog.colors'))->assertOk()->assertDontSee('Ajouter');
});

it('affiche les boutons d\'ajout avec la permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('customers.index'))->assertSee('Ajouter un client');
});
