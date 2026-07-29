<?php

use App\Models\Follow;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot follow anyone', function () {
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.users.follow', $this->other))
        ->assertUnauthorized();
});

test('a user can follow another user', function () {
    $this->postJson(route('api.v1.users.follow', $this->other))
        ->assertCreated()
        ->assertJsonPath('data.is_following', true)
        ->assertJsonPath('data.followers_count', 1)
        ->assertJsonPath('data.user.id', $this->other->id)
        ->assertJsonPath('data.user.full_name', $this->other->full_name)
        ->assertJsonMissingPath('data.user.email');

    expect(Follow::count())->toBe(1);
    expect($this->user->refresh()->following_count)->toBe(1);
    expect($this->other->refresh()->followers_count)->toBe(1);
});

test('following twice does not count twice', function () {
    $this->postJson(route('api.v1.users.follow', $this->other))->assertCreated();

    $this->postJson(route('api.v1.users.follow', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', true)
        ->assertJsonPath('data.followers_count', 1);

    expect(Follow::count())->toBe(1);
    expect($this->user->refresh()->following_count)->toBe(1);
    expect($this->other->refresh()->followers_count)->toBe(1);
});

test('a user cannot follow themselves', function () {
    $this->postJson(route('api.v1.users.follow', $this->user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    expect(Follow::count())->toBe(0);
    expect($this->user->refresh()->following_count)->toBe(0);
});

test('a user can unfollow someone they follow', function () {
    $this->postJson(route('api.v1.users.follow', $this->other))->assertCreated();

    $this->deleteJson(route('api.v1.users.unfollow', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', false)
        ->assertJsonPath('data.followers_count', 0);

    expect(Follow::count())->toBe(0);
    expect($this->user->refresh()->following_count)->toBe(0);
    expect($this->other->refresh()->followers_count)->toBe(0);
});

test('unfollowing someone never followed leaves the counts alone', function () {
    $this->deleteJson(route('api.v1.users.unfollow', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', false)
        ->assertJsonPath('data.followers_count', 0);

    expect($this->user->refresh()->following_count)->toBe(0);
    expect($this->other->refresh()->followers_count)->toBe(0);
});

test('unfollowing does not disturb other people following the same user', function () {
    $bystander = User::factory()->create();

    Follow::factory()->from($bystander)->to($this->other)->create();
    $this->other->increment('followers_count');

    $this->postJson(route('api.v1.users.follow', $this->other))->assertCreated();

    $this->deleteJson(route('api.v1.users.unfollow', $this->other))
        ->assertOk()
        ->assertJsonPath('data.followers_count', 1);

    expect(Follow::count())->toBe(1);
});

test('following an unknown user is a 404', function () {
    $this->postJson(route('api.v1.users.follow', '99999999-9999-9999-9999-999999999999'))
        ->assertNotFound();
});
