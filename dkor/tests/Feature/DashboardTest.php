<?php

use App\Models\User;

test('guests can view the login front page', function () {
    $response = $this->get('/');
    $response->assertOk();
    $response->assertSee('Log in to your account');
});

test('authenticated users can visit the home page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('home'));
    $response->assertOk();
});
