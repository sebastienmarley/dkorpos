<?php

use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Schedules\Appointments;
use App\Livewire\Schedules\Index as SchedulesIndex;
use App\Livewire\Schedules\ScheduleEdit;
use App\Livewire\UserForm;
use App\Livewire\Users\Index as UsersIndex;
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
    Route::get('users', UsersIndex::class)->name('users.index');
    Route::get('users/create', UserForm::class)->name('users.create');
    Route::get('users/{user}/edit', UserForm::class)->name('users.edit');

    Route::get('customers', CustomersIndex::class)->name('customers.index');

    Route::get('schedules', SchedulesIndex::class)->name('schedules.index');
    Route::get('schedules/schedule-edit', ScheduleEdit::class)->name('schedules.schedule-edit');
    Route::get('schedules/appointments', Appointments::class)->name('schedules.appointments');
});

require __DIR__.'/settings.php';
