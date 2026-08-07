<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\User;
use App\Models\WinMovement;
use Laravel\Sanctum\Sanctum;

/**
 * Bringing your earlier wins to a circle you joined later.
 *
 * A win is placed in the circles its author was in at the time, so a circle
 * joined afterwards has none of their history — the wall says somebody who has
 * posted for months has never shared a thing. This fills that in, for any win
 * of theirs: the author is the one asking, from inside the circle.
 */
beforeEach(function () {
    $this->author = User::factory()->create();
    $this->circle = Circle::factory()->create(['name' => 'Late Joiners']);
    Sanctum::actingAs($this->author);
});

function ownPost(string $visibility): Post
{
    $post = Post::factory()->create([
        'user_id' => test()->author->id,
        'visibility' => $visibility,
    ]);

    WinMovement::factory()->for($post, 'post')->create();

    return $post;
}

function joinCircle(): void
{
    CircleMembership::create([
        'user_id' => test()->author->id,
        'circle_id' => test()->circle->id,
        'joined_at' => now(),
    ]);
}

test('the circle says how many earlier wins could be brought in', function () {
    joinCircle();
    ownPost(Post::VISIBILITY_PUBLIC);
    ownPost(Post::VISIBILITY_ALL_CIRCLES);
    ownPost(Post::VISIBILITY_CUSTOM);

    $this->getJson(route('api.v1.circles.show', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.syncable_posts_count', 3);
});

test('a win already on the wall is not offered again', function () {
    joinCircle();
    $there = ownPost(Post::VISIBILITY_CUSTOM);
    $this->circle->posts()->attach($there);
    ownPost(Post::VISIBILITY_CUSTOM);

    $this->getJson(route('api.v1.circles.show', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.syncable_posts_count', 1);
});

test('sharing them in puts every one of your wins on the wall', function () {
    joinCircle();
    // Whatever each was shared to when it was written: the author is standing
    // in the circle asking for them, which is the choice being made again.
    $open = ownPost(Post::VISIBILITY_PUBLIC);
    $circles = ownPost(Post::VISIBILITY_ALL_CIRCLES);
    $picked = ownPost(Post::VISIBILITY_CUSTOM);

    $this->postJson(route('api.v1.circles.sync', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.shared', 3)
        ->assertJsonPath('data.syncable_posts_count', 0);

    expect($this->circle->posts()->whereKey($open->id)->exists())->toBeTrue()
        ->and($this->circle->posts()->whereKey($circles->id)->exists())->toBeTrue()
        ->and($this->circle->posts()->whereKey($picked->id)->exists())->toBeTrue();
});

test('what a win was shared to is left as it was', function () {
    joinCircle();
    $picked = ownPost(Post::VISIBILITY_CUSTOM);

    $this->postJson(route('api.v1.circles.sync', $this->circle))->assertOk();

    // The wall it lands on is a circle added to its list, not a rewrite of the
    // answer its author gave to "who is this for".
    expect($picked->fresh()->visibility)->toBe(Post::VISIBILITY_CUSTOM);
});

test('pressing it twice does not share anything twice', function () {
    joinCircle();
    ownPost(Post::VISIBILITY_PUBLIC);

    $this->postJson(route('api.v1.circles.sync', $this->circle))->assertOk();
    $this->postJson(route('api.v1.circles.sync', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.shared', 0);

    expect($this->circle->posts()->count())->toBe(1);
});

test('it never unshares what somebody put there deliberately', function () {
    joinCircle();
    $deliberate = ownPost(Post::VISIBILITY_CUSTOM);
    $this->circle->posts()->attach($deliberate);

    ownPost(Post::VISIBILITY_PUBLIC);

    $this->postJson(route('api.v1.circles.sync', $this->circle))->assertOk();

    // Two now: the one already there, and the public one just brought in.
    expect($this->circle->posts()->count())->toBe(2)
        ->and($this->circle->posts()->whereKey($deliberate->id)->exists())->toBeTrue();
});

test('somebody who has not joined cannot post into the wall this way', function () {
    ownPost(Post::VISIBILITY_PUBLIC);

    $this->postJson(route('api.v1.circles.sync', $this->circle))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('circle');

    expect($this->circle->posts()->count())->toBe(0);
});

test('only your own wins come with you', function () {
    joinCircle();

    $mine = ownPost(Post::VISIBILITY_PUBLIC);
    $theirs = Post::factory()->create([
        'user_id' => User::factory()->create()->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->postJson(route('api.v1.circles.sync', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.shared', 1);

    expect($this->circle->posts()->whereKey($mine->id)->exists())->toBeTrue()
        ->and($this->circle->posts()->whereKey($theirs->id)->exists())->toBeFalse();
});
