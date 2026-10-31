<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('firstname', 'Test')
        ->set('lastname', 'User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->firstname)->toEqual('Test');
    expect($user->lastname)->toEqual('User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('firstname', 'Test')
        ->set('lastname', 'User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
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

test('user can deactivate their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh()->is_active)->toBeFalse();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to deactivate account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh()->is_active)->toBeTrue();
});
