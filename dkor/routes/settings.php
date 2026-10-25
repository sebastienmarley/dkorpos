<?php

use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');

    Route::livewire('settings/two-factor', TwoFactor::class)
        ->middleware('password.confirm')
        ->name('two-factor.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings/appearance', 'settings/profile')->name('appearance.edit');
});
