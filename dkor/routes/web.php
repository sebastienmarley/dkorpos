<?php

use App\Livewire\UserForm;
use App\Models\User;
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
    Route::get('users/create', UserForm::class)->name('users.create');
    Route::get('users/{user}/edit', UserForm::class)->name('users.edit');
});

require __DIR__.'/settings.php';
