<?php

use App\Models\User;

it('generates a username from the first and last names', function () {
    expect(User::generateUniqueUsername('Jane', 'Doe'))->toBe('janedoe');
});

it('increments the username when the base username is already taken', function () {
    User::factory()->create([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'username' => 'janedoe',
        'email' => 'jane.doe@example.com',
    ]);

    expect(User::generateUniqueUsername('Jane', 'Doe'))->toBe('janedoe1');
});

it('does not accept a manually supplied username for new records', function () {
    $user = new User([
        'firstname' => 'Jane',
        'lastname' => 'Doe',
        'username' => 'manual-user',
        'email' => 'manual@example.com',
        'password' => 'password',
    ]);

    $user->save();

    expect($user->username)->toBe('janedoe');
    expect($user->username)->not->toBe('manual-user');
});
