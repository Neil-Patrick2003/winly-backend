<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use App\Models\WinMovement;
use Laravel\Sanctum\Sanctum;

/**
 * Who may read a win.
 *
 * A circle used to be where a post was placed and never who it was kept from.
 * It is a boundary now, and a boundary is only worth as much as the path that
 * forgets it — so every way into a post is named here and asked separately.
 * A leak does not announce itself; nothing fails, somebody simply sees
 * something that was not for them.
 */
beforeEach(function () {
    $this->author = User::factory()->create();
    $this->member = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->circle = Circle::factory()->create(['owner_id' => $this->author->id]);

    foreach ([$this->author, $this->member] as $person) {
        CircleMembership::create([
            'user_id' => $person->id,
            'circle_id' => $this->circle->id,
            'joined_at' => now(),
        ]);
    }
});

/** A win shared into the circle and nowhere else. */
function circlePost(): Post
{
    $post = Post::factory()->create([
        'user_id' => test()->author->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
    ]);

    WinMovement::factory()->for($post, 'post')->create();
    test()->circle->posts()->attach($post);

    return $post;
}

/** A win shared with everybody. */
function publicPost(): Post
{
    $post = Post::factory()->create([
        'user_id' => test()->author->id,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    WinMovement::factory()->for($post, 'post')->create();

    return $post;
}

test('a circle win stays out of a stranger feed but reaches a member', function () {
    $post = circlePost();

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonMissing(['id' => $post->id]);

    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id);
});

test('a public win reaches everybody', function () {
    $post = publicPost();

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id);
});

test('a profile shows a stranger only what they may read', function () {
    $open = publicPost();
    $closed = circlePost();

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.users.posts', $this->author))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);

    // The same profile, opened by somebody in the circle, carries both.
    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.users.posts', $this->author))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($closed->fresh())->not->toBeNull();
});

test('the author always reads their own win, shared with nobody or not', function () {
    $post = Post::factory()->create([
        'user_id' => $this->author->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
    ]);

    Sanctum::actingAs($this->author);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('a stranger cannot open a circle win directly', function () {
    $post = circlePost();

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.show', $post))->assertForbidden();

    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('a stranger cannot reach the thread under a circle win', function () {
    $post = circlePost();

    Sanctum::actingAs($this->stranger);

    $this->getJson(route('api.v1.posts.comments.index', $post))->assertForbidden();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Nice.'])
        ->assertForbidden();
});

test('a stranger cannot react to or keep a circle win', function () {
    $post = circlePost();

    Sanctum::actingAs($this->stranger);

    $this->putJson(route('api.v1.posts.like', $post))->assertForbidden();
    $this->putJson(route('api.v1.posts.save', $post))->assertForbidden();

    expect($post->fresh()->likes_count)->toBe(0)
        ->and(SavedPost::count())->toBe(0);
});

test('a kept win drops out of the pile once the reader leaves the circle', function () {
    $post = circlePost();

    Sanctum::actingAs($this->member);
    $this->putJson(route('api.v1.posts.save', $post))->assertCreated();
    $this->getJson(route('api.v1.posts.saved'))->assertOk()->assertJsonCount(1, 'data');

    // Kept once and readable now are different questions, and the pile is not
    // a way around the boundary.
    CircleMembership::query()
        ->where('user_id', $this->member->id)
        ->where('circle_id', $this->circle->id)
        ->delete();

    $this->getJson(route('api.v1.posts.saved'))->assertOk()->assertJsonCount(0, 'data');
});

test('sharing publicly places the win in no circle', function () {
    Sanctum::actingAs($this->author);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.visibility', 'public')
        // Readable by everybody, and on nobody's wall. A circle's wall is what
        // that group was given; putting a public win there is a second act, and
        // the circle's own screen is where it is offered.
        ->assertJsonCount(0, 'data.circles');

    $post = Post::sole();

    expect($post->circles()->count())->toBe(0);

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('sharing with all circles resolves to the ones the author is in now', function () {
    $second = Circle::factory()->create(['owner_id' => $this->author->id]);
    CircleMembership::create([
        'user_id' => $this->author->id,
        'circle_id' => $second->id,
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($this->author);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'all_circles',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.visibility', 'all_circles')
        ->assertJsonCount(2, 'data.circles');
});

test('a circle joined later never gains an older win', function () {
    Sanctum::actingAs($this->author);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'all_circles',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])->assertCreated();

    // Joining afterwards. The setting was a snapshot, not a standing order.
    $later = Circle::factory()->create(['owner_id' => $this->author->id]);
    CircleMembership::create([
        'user_id' => $this->author->id,
        'circle_id' => $later->id,
        'joined_at' => now(),
    ]);

    expect(Post::sole()->circles()->count())->toBe(1)
        ->and($later->posts()->count())->toBe(0);
});

test('picking circles requires naming them, and the other two refuse a list', function () {
    Sanctum::actingAs($this->author);

    $win = [['type' => 'movement', 'movement_type' => 'run']];

    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'custom', 'wins' => $win])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('circle_ids');

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'circle_ids' => [$this->circle->id],
        'wins' => $win,
    ])->assertUnprocessable()->assertJsonValidationErrors('circle_ids');

    $this->postJson(route('api.v1.posts.store'), ['wins' => $win])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('visibility');
});

test('a win cannot be shared into a circle its author is not in', function () {
    $theirs = Circle::factory()->create(['owner_id' => $this->stranger->id]);

    Sanctum::actingAs($this->author);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'custom',
        'circle_ids' => [$theirs->id],
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])->assertUnprocessable()->assertJsonValidationErrors('circle_ids.0');
});

test('narrowing a public win to a circle hides it from everyone else', function () {
    $post = publicPost();

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();

    Sanctum::actingAs($this->author);
    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'custom',
        'circle_ids' => [$this->circle->id],
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])
        ->assertOk()
        ->assertJsonPath('data.visibility', 'custom');

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.show', $post))->assertForbidden();

    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('changing a circle win to public takes it off that wall', function () {
    $post = circlePost();

    Sanctum::actingAs($this->author);
    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])->assertOk();

    /*
     * Public means readable by everybody and placed in no circle, so choosing
     * it is choosing both halves — the win comes off the wall it was on.
     *
     * Recorded here because it is the surprising half of the rule: a post
     * deliberately given to one group leaves that group when it is opened up.
     * The circle's own screen is how it goes back, for this win and every other
     * public one not on that wall.
     */
    expect($post->fresh()->circles()->count())->toBe(0);

    Sanctum::actingAs($this->stranger);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('a private circle closes every door, and leaves the members their own', function () {
    /*
     * The sweep, rather than one route taken as a proof of the rest.
     *
     * Privacy is decided by circle *membership* and always was — turning the
     * circle private changes who can find it, not who can read its wall. That
     * is the right answer, but it means the guarantee rests on every path
     * remembering to ask, and a path that forgets fails quietly.
     */
    $this->circle->update(['is_private' => true]);
    $post = circlePost();

    Sanctum::actingAs($this->stranger);

    // The win itself, by each route that reaches one.
    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonMissing(['id' => $post->id]);
    $this->getJson(route('api.v1.users.posts', $this->author))
        ->assertOk()
        ->assertJsonMissing(['id' => $post->id]);
    $this->getJson(route('api.v1.posts.show', $post))->assertForbidden();
    $this->getJson(route('api.v1.posts.comments.index', $post))->assertForbidden();
    $this->putJson(route('api.v1.posts.like', $post))->assertForbidden();
    $this->putJson(route('api.v1.posts.save', $post))->assertForbidden();

    // And the circle around it — a public one answers all four of these.
    $this->getJson(route('api.v1.circles.show', $this->circle))->assertForbidden();
    $this->getJson(route('api.v1.circles.posts', $this->circle))->assertForbidden();
    $this->getJson(route('api.v1.circles.members', $this->circle))->assertForbidden();
    $this->postJson(route('api.v1.circles.join', $this->circle))->assertForbidden();

    // The people inside still have it. A boundary that shut them out too would
    // be a broken circle rather than a private one.
    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonPath('data.0.id', $post->id);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});
