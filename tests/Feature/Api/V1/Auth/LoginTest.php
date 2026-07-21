<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'username' => 'neil_m',
        'email' => 'neil@example.com',
    ]);
});

test('a user can log in and receive an api token', function () {
    $response = $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonPath('user.username', 'neil_m')
        ->assertJsonStructure(['user' => ['id', 'email'], 'token']);

    $token = PersonalAccessToken::findToken($response->json('token'));

    expect($token)->not->toBeNull();
    expect($token->name)->toBe('iPhone 17');
    expect($token->tokenable->is($this->user))->toBeTrue();
});

test('the issued token authenticates api requests', function () {
    $token = $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ])->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.email', 'neil@example.com');
});

test('login fails with the wrong password', function () {
    $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'wrong-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($this->user->tokens)->toHaveCount(0);
});

test('login fails for an unknown email', function () {
    $this->postJson(route('api.v1.login'), [
        'email' => 'nobody@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('a soft deleted user cannot log in', function () {
    $this->user->delete();

    $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ])->assertUnprocessable();
});

test('login requires all fields', function () {
    $this->postJson(route('api.v1.login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

test('login is rate limited after five failed attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.login'), [
            'email' => 'neil@example.com',
            'password' => 'wrong-password',
            'device_name' => 'iPhone 17',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ])->assertStatus(429);
});
