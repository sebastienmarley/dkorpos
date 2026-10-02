<?php

use App\Livewire\Accounting\Currencies;
use App\Livewire\Admin\Permissions;
use App\Livewire\Admin\Positions;
use App\Livewire\Admin\Roles;
use App\Livewire\Catalog\Categories;
use App\Livewire\Catalog\Colors;
use App\Livewire\Catalog\Departments;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\Holidays;
use App\Livewire\Schedules\Index as SchedulesIndex;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\Schedules\Templates;
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

    Route::get('suppliers', SuppliersIndex::class)->middleware('can:suppliers.view')->name('suppliers.index');
    Route::get('suppliers/{supplier}', SupplierShow::class)->middleware('can:suppliers.view')->name('suppliers.show');

    Route::get('products', ProductsIndex::class)->middleware('can:products.view')->name('products.index');
    Route::get('products/{product}', ProductShow::class)->middleware('can:products.view')->name('products.show');

    Route::get('catalog/departments', Departments::class)->middleware('can:departments.view')->name('catalog.departments');
    Route::get('catalog/categories', Categories::class)->middleware('can:categories.view')->name('catalog.categories');
    Route::get('catalog/colors', Colors::class)->middleware('can:colors.view')->name('catalog.colors');

    Route::get('accounting/currencies', Currencies::class)->middleware('can:currencies.view')->name('accounting.currencies');

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
