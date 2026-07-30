<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('a user can log out and the token is revoked', function () {
    $token = $this->user->createToken('iPhone 17')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.logout'))
        ->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);
});

test('a revoked token is rejected', function () {
    $token = $this->user->createToken('iPhone 17')->plainTextToken;

    $this->user->tokens()->delete();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.user'))
        ->assertUnauthorized();
});

test('logging out only revokes the current device token', function () {
    $phoneToken = $this->user->createToken('iPhone 17')->plainTextToken;
    $this->user->createToken('iPad');

    $this->withHeader('Authorization', "Bearer {$phoneToken}")
        ->postJson(route('api.v1.logout'))
        ->assertNoContent();

    expect($this->user->tokens()->pluck('name')->all())->toBe(['iPad']);
});

test('guests cannot log out', function () {
    $this->postJson(route('api.v1.logout'))->assertUnauthorized();
});
