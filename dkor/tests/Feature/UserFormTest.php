<?php

use App\Livewire\UserForm;
use App\Models\User;
use Livewire\Livewire;

it('creates a user with an auto-generated unique username', function () {
    $response = Livewire::test(UserForm::class)
        ->set('firstname', 'Jane')
        ->set('lastname', 'Doe')
        ->set('email', 'jane.doe@example.com')
        ->set('role', 'user')
        ->set('is_active', true)
        ->call('save');

    $response->assertHasNoErrors();

    $user = User::where('email', 'jane.doe@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->username)->toBe('janedoe');
    expect($user->is_active)->toBeTrue();
});

it('increments the username when the base username already exists', function () {
    User::factory()->create([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'username' => 'janedoe',
        'email' => 'original@example.com',
    ]);

    $response = Livewire::test(UserForm::class)
        ->set('firstname', 'Jane')
        ->set('lastname', 'Doe')
        ->set('email', 'jane.doe.duplicate@example.com')
        ->set('role', 'user')
        ->set('is_active', true)
        ->call('save');

    $response->assertHasNoErrors();

    $user = User::where('email', 'jane.doe.duplicate@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->username)->toBe('janedoe1');
});
