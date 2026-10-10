<?php

use App\Livewire\Accounting\Currencies;
use App\Livewire\Accounting\Invoices\Create as InvoiceCreate;
use App\Livewire\Accounting\Invoices\Form as InvoiceForm;
use App\Livewire\Accounting\Invoices\Index as InvoicesIndex;
use App\Livewire\Accounting\Invoices\Show as InvoiceShow;
use App\Livewire\Accounting\PaymentMethods;
use App\Livewire\Accounting\Payroll;
use App\Livewire\Accounting\Taxes;
use App\Livewire\Admin\Permissions;
use App\Livewire\Admin\Positions;
use App\Livewire\Admin\Roles;
use App\Livewire\Catalog\Categories;
use App\Livewire\Catalog\Colors;
use App\Livewire\Catalog\Departments;
use App\Livewire\Catalog\PriceLists;
use App\Livewire\Catalog\PriceListShow;
use App\Livewire\Catalog\Services;
use App\Livewire\CustomerOrders\Index as CustomerOrdersIndex;
use App\Livewire\CustomerOrders\Show as CustomerOrderShow;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Inventory\Movements as InventoryMovements;
use App\Livewire\Orders\Index as SupplierOrdersIndex;
use App\Livewire\Orders\Show as SupplierOrderShow;
use App\Livewire\Parts\Index as PartsIndex;
use App\Livewire\Parts\Show as PartShow;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Receptions\Create as ReceptionCreate;
use App\Livewire\Receptions\Index as ReceptionsIndex;
use App\Livewire\Receptions\Show as ReceptionShow;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\Holidays;
use App\Livewire\Schedules\Index as SchedulesIndex;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\Schedules\Templates;
use App\Livewire\Stores\Index as StoresIndex;
use App\Livewire\Stores\Show as StoreShow;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Users\Show as UserShow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('home');
    }

    return view('login');
})->name('landing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('home', 'home')->name('home');
    Route::get('users', UsersIndex::class)->middleware('can:users.view')->name('users.index');
    Route::get('users/{user}', UserShow::class)->middleware('can:users.view')->name('users.show');

    Route::get('customers', CustomersIndex::class)->middleware('can:customers.view')->name('customers.index');

    Route::get('customer-orders', CustomerOrdersIndex::class)->middleware('can:customer_orders.view')->name('customer-orders.index');
    Route::get('customer-orders/{order}', CustomerOrderShow::class)->middleware('can:customer_orders.view')->name('customer-orders.show');

    Route::get('suppliers', SuppliersIndex::class)->middleware('can:suppliers.view')->name('suppliers.index');
    Route::get('suppliers/{supplier}', SupplierShow::class)->middleware('can:suppliers.view')->name('suppliers.show');

    Route::get('supplier-orders', SupplierOrdersIndex::class)->middleware('can:supplier_orders.view')->name('supplier-orders.index');
    Route::get('supplier-orders/{order}', SupplierOrderShow::class)->middleware('can:supplier_orders.view')->name('supplier-orders.show');

    Route::get('receptions', ReceptionsIndex::class)->middleware('can:receptions.view')->name('receptions.index');
    Route::get('receptions/create', ReceptionCreate::class)->middleware('can:receptions.create')->name('receptions.create');
    Route::get('receptions/{reception}', ReceptionShow::class)->middleware('can:receptions.view')->name('receptions.show');

    Route::get('inventory/movements', InventoryMovements::class)->middleware('can:inventory.view')->name('inventory.movements');

    Route::get('products', ProductsIndex::class)->middleware('can:products.view')->name('products.index');
    Route::get('products/{product}', ProductShow::class)->middleware('can:products.view')->name('products.show');

    Route::get('parts', PartsIndex::class)->middleware('can:parts.view')->name('parts.index');
    Route::get('parts/{part}', PartShow::class)->middleware('can:parts.view')->name('parts.show');

    Route::get('catalog/departments', Departments::class)->middleware('can:departments.view')->name('catalog.departments');
    Route::get('catalog/categories', Categories::class)->middleware('can:categories.view')->name('catalog.categories');
    Route::get('catalog/services', Services::class)->middleware('can:services.view')->name('catalog.services');
    Route::get('catalog/colors', Colors::class)->middleware('can:colors.view')->name('catalog.colors');
    Route::get('catalog/price-lists', PriceLists::class)->middleware('can:price_lists.view')->name('catalog.price-lists');
    Route::get('catalog/price-lists/{priceList}', PriceListShow::class)->middleware('can:price_lists.view')->name('catalog.price-lists.show');

    Route::get('accounting/invoices', InvoicesIndex::class)->middleware('can:invoices.view')->name('accounting.invoices');
    Route::get('accounting/invoices/receptions/{reception}', InvoiceForm::class)->middleware('can:invoices.view')->name('accounting.invoices.reception');
    Route::get('accounting/invoices/create', InvoiceCreate::class)->middleware('can:invoices.create')->name('accounting.invoices.create');
    Route::get('accounting/invoices/{invoice}', InvoiceShow::class)->whereNumber('invoice')->middleware('can:invoices.view')->name('accounting.invoices.show');
    Route::get('accounting/invoices/orders/{order}', InvoiceForm::class)->middleware('can:invoices.view')->name('accounting.invoices.order');
    Route::get('accounting/payroll', Payroll::class)->middleware('can:payroll.view')->name('accounting.payroll');
    Route::get('accounting/payment-methods', PaymentMethods::class)->middleware('can:payment_methods.view')->name('accounting.payment-methods');
    Route::get('accounting/taxes', Taxes::class)->middleware('can:taxes.view')->name('accounting.taxes');
    Route::get('accounting/currencies', Currencies::class)->middleware('can:currencies.view')->name('accounting.currencies');

    Route::get('stores', StoresIndex::class)->middleware('can:stores.view')->name('stores.index');
    Route::get('stores/{store}', StoreShow::class)->middleware('can:stores.view')->name('stores.show');

    Route::get('admin/positions', Positions::class)->middleware('can:positions.manage')->name('admin.positions');
    Route::get('admin/roles', Roles::class)->middleware('can:roles.manage')->name('admin.roles');
    Route::get('admin/permissions', Permissions::class)->middleware('can:permissions.manage')->name('admin.permissions');

    Route::get('schedules', SchedulesIndex::class)->middleware('can:schedules.view')->name('schedules.index');
    Route::get('schedules/schedule-edit', ScheduleEdit::class)->middleware('can:schedule_management.view')->name('schedules.schedule-edit');
    Route::get('schedules/appointments', Appointments::class)->middleware('can:appointments.view')->name('schedules.appointments');
    Route::get('schedules/templates', Templates::class)->middleware('can:schedule_templates.view')->name('schedules.templates');
    Route::get('schedules/holidays', Holidays::class)->middleware('can:holidays.view')->name('schedules.holidays');
});

require __DIR__.'/settings.php';
