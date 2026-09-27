<?php

use App\Models\User;
use Illuminate\Support\Carbon;

it('désactive les employés dont le last_day est passé', function () {
    Carbon::setTestNow('2026-09-27');

    $expired = User::factory()->create(['is_active' => true, 'last_day' => '2026-09-26']);

    $this->artisan('employees:deactivate-expired')->assertSuccessful();

    expect($expired->fresh()->is_active)->toBeFalse();
});

it('ne désactive pas les employés dont le last_day est aujourd\'hui', function () {
    Carbon::setTestNow('2026-09-27');

    $stillActive = User::factory()->create(['is_active' => true, 'last_day' => '2026-09-27']);

    $this->artisan('employees:deactivate-expired')->assertSuccessful();

    expect($stillActive->fresh()->is_active)->toBeTrue();
});

it('ne désactive pas les employés sans last_day', function () {
    Carbon::setTestNow('2026-09-27');

    $noLastDay = User::factory()->create(['is_active' => true, 'last_day' => null]);

    $this->artisan('employees:deactivate-expired')->assertSuccessful();

    expect($noLastDay->fresh()->is_active)->toBeTrue();
});

it('ne touche pas les employés déjà inactifs', function () {
    Carbon::setTestNow('2026-09-27');

    $alreadyInactive = User::factory()->create(['is_active' => false, 'last_day' => '2026-09-20']);

    $this->artisan('employees:deactivate-expired')->assertSuccessful();

    expect($alreadyInactive->fresh()->is_active)->toBeFalse();
});
