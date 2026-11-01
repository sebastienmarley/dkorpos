<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('user can reset their own password', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Profile::class)
        ->assertSet('generatedPassword', null)
        ->set('currentPassword', 'password')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertSet('generatedPassword', fn ($value) => filled($value) && strlen($value) >= 12);

    $user->refresh();

    expect(Hash::check($component->get('generatedPassword'), $user->password))->toBeTrue();
    expect($user->last_modified_by)->toBe($user->id);
});

test('current password is required to reset own password', function () {
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('currentPassword', 'wrong-password')
        ->call('resetPassword')
        ->assertHasErrors(['currentPassword'])
        ->assertSet('generatedPassword', null);

    expect($user->fresh()->password)->toBe($originalHash);
});

test('own password reset is blocked after too many wrong attempts', function () {
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs($user);

    $component = Livewire::test(Profile::class);

    foreach (range(1, 5) as $attempt) {
        $component->set('currentPassword', 'wrong-password')->call('resetPassword');
    }

    $component
        ->set('currentPassword', 'password')
        ->call('resetPassword')
        ->assertHasErrors(['currentPassword'])
        ->assertSet('generatedPassword', null);

    expect($user->fresh()->password)->toBe($originalHash);
});
