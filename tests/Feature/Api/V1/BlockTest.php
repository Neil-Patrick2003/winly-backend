<?php

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->them = User::factory()->create(['username' => 'troll']);
    Sanctum::actingAs($this->me);
});

function block(User $who): void
{
    test()->postJson(route('api.v1.users.block', $who))->assertOk()->assertJsonPath('data.is_blocked', true);
}

test('guests cannot block anyone', function () {
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.users.block', $this->them))->assertUnauthorized();
});

test('you cannot block yourself', function () {
    $this->postJson(route('api.v1.users.block', $this->me))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');
});

test('blocking twice changes nothing', function () {
    block($this->them);
    block($this->them);

    expect($this->me->blockedUsers()->count())->toBe(1);
});

test('blocking removes follows both ways and fixes the counts', function () {
    Follow::factory()->from($this->me)->to($this->them)->create();
    Follow::factory()->from($this->them)->to($this->me)->create();
    $this->me->update(['followers_count' => 1, 'following_count' => 1]);
    $this->them->update(['followers_count' => 1, 'following_count' => 1]);

    block($this->them);

    expect(Follow::count())->toBe(0)
        ->and($this->me->fresh()->followers_count)->toBe(0)
        ->and($this->me->fresh()->following_count)->toBe(0)
        ->and($this->them->fresh()->followers_count)->toBe(0)
        ->and($this->them->fresh()->following_count)->toBe(0);
});

test('their posts leave my feed and their profile wall', function () {
    $theirs = Post::factory()->for($this->them)->create();
    $other = Post::factory()->create();

    block($this->them);

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $other->id);

    $this->getJson(route('api.v1.users.posts', $this->them))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.posts.show', $theirs))->assertForbidden();
});

test('a block hides me from them too', function () {
    $mine = Post::factory()->for($this->me)->create();

    block($this->them);

    Sanctum::actingAs($this->them);

    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.posts.show', $mine))->assertForbidden();
    $this->getJson(route('api.v1.users.show', $this->me))->assertNotFound();
    $this->postJson(route('api.v1.users.follow', $this->me))->assertNotFound();
    $this->postJson(route('api.v1.posts.comments.store', $mine), ['text' => 'hi'])->assertForbidden();
    $this->putJson(route('api.v1.posts.like', $mine))->assertForbidden();
});

test('their comments and likes are hidden on other posts', function () {
    $post = Post::factory()->create();
    Comment::factory()->create(['post_id' => $post->id, 'user_id' => $this->them->id]);
    $kept = Comment::factory()->create(['post_id' => $post->id]);
    PostLike::factory()->create(['post_id' => $post->id, 'user_id' => $this->them->id]);

    block($this->them);

    $this->getJson(route('api.v1.posts.comments.index', $post))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kept->id);

    $this->getJson(route('api.v1.posts.likes', $post))->assertOk()->assertJsonCount(0, 'data');
});

test('they disappear from search and from my notifications', function () {
    Post::factory()->for($this->them)->create();
    Notification::factory()->create(['user_id' => $this->me->id, 'actor_id' => $this->them->id]);

    block($this->them);

    $this->getJson(route('api.v1.discover', ['q' => 'troll']))
        ->assertOk()
        ->assertJsonMissing(['id' => $this->them->id]);

    $this->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.notifications.unread'))->assertOk()->assertJsonPath('data.unread', 0);
});

test('their profile still opens for me, marked blocked, so I can unblock', function () {
    block($this->them);

    $this->getJson(route('api.v1.users.show', $this->them))
        ->assertOk()
        ->assertJsonPath('data.is_blocked', true);

    $this->getJson(route('api.v1.blocks.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->them->id);
});

test('unblocking brings their posts back', function () {
    Post::factory()->for($this->them)->create();

    block($this->them);

    $this->deleteJson(route('api.v1.users.unblock', $this->them))
        ->assertOk()
        ->assertJsonPath('data.is_blocked', false);

    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(route('api.v1.users.show', $this->them))->assertJsonPath('data.is_blocked', false);
});
