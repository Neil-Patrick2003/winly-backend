<?php

use App\Models\Notification;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actor = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/** An unread alert addressed to the signed-in user. */
function alertFor(User $owner, User $actor): Notification
{
    return Notification::factory()->create([
        'user_id' => $owner->id,
        'actor_id' => $actor->id,
        'type' => 'like',
        'message' => 'liked your win',
        'is_read' => false,
    ]);
}

test('reading one alert leaves the others alone', function () {
    $opened = alertFor($this->user, $this->actor);
    $untouched = alertFor($this->user, $this->actor);
    $alsoUntouched = alertFor($this->user, $this->actor);

    $this->postJson(route('api.v1.notifications.read.one', $opened))
        ->assertOk()
        ->assertJsonPath('data.id', $opened->id)
        // The two still waiting, reported so the bell can settle without asking.
        ->assertJsonPath('data.unread', 2);

    expect($opened->fresh()->is_read)->toBeTrue()
        ->and($untouched->fresh()->is_read)->toBeFalse()
        ->and($alsoUntouched->fresh()->is_read)->toBeFalse();
});

test('reading one twice is not an error and does not go negative', function () {
    $alert = alertFor($this->user, $this->actor);

    $this->postJson(route('api.v1.notifications.read.one', $alert))->assertOk();
    $this->postJson(route('api.v1.notifications.read.one', $alert))
        ->assertOk()
        ->assertJsonPath('data.unread', 0);
});

test('somebody else alert cannot be read, and does not admit to existing', function () {
    $theirs = alertFor($this->actor, $this->user);

    $this->postJson(route('api.v1.notifications.read.one', $theirs))->assertNotFound();

    expect($theirs->fresh()->is_read)->toBeFalse();
});

test('marking everything read is still available as its own action', function () {
    alertFor($this->user, $this->actor);
    alertFor($this->user, $this->actor);

    $this->postJson(route('api.v1.notifications.read'))
        ->assertOk()
        ->assertJsonPath('data.unread', 0);

    expect(Notification::query()->where('user_id', $this->user->id)->where('is_read', false)->count())
        ->toBe(0);
});

test('listing alerts does not read them', function () {
    alertFor($this->user, $this->actor);
    alertFor($this->user, $this->actor);

    $this->getJson(route('api.v1.notifications.index'))->assertOk();

    // Looking at a list is not the same as having read what is on it.
    $this->getJson(route('api.v1.notifications.unread'))
        ->assertOk()
        ->assertJsonPath('data.unread', 2);
});
