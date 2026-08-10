<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot read who liked a post', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.posts.likes', $this->post))->assertUnauthorized();
});

test('it lists everyone who liked a post, most recent first', function () {
    $early = User::factory()->create(['full_name' => 'Early']);
    $late = User::factory()->create(['full_name' => 'Late']);

    PostLike::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $early->id,
        'created_at' => now()->subHour(),
    ]);
    PostLike::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $late->id,
        'created_at' => now(),
    ]);

    $this->getJson(route('api.v1.posts.likes', $this->post))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.full_name', 'Late')
        ->assertJsonPath('data.1.full_name', 'Early')
        ->assertJsonStructure(['data' => [['id', 'full_name', 'username', 'avatar_url', 'liked_at']]])
        ->assertJsonMissingPath('data.0.email');
});

test('likes on other posts do not appear', function () {
    $other = Post::factory()->create();

    PostLike::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => User::factory()->create(['full_name' => 'Ours'])->id,
    ]);
    PostLike::factory()->create([
        'post_id' => $other->id,
        'user_id' => User::factory()->create(['full_name' => 'Theirs'])->id,
    ]);

    $this->getJson(route('api.v1.posts.likes', $this->post))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Ours');
});

test('each row says whether the caller follows that person', function () {
    $known = User::factory()->create(['full_name' => 'Known']);
    $stranger = User::factory()->create(['full_name' => 'Stranger']);

    PostLike::factory()->create(['post_id' => $this->post->id, 'user_id' => $known->id]);
    PostLike::factory()->create(['post_id' => $this->post->id, 'user_id' => $stranger->id]);

    $this->postJson(route('api.v1.users.follow', $known))->assertSuccessful();

    $rows = collect(
        $this->getJson(route('api.v1.posts.likes', $this->post))->assertOk()->json('data')
    )->keyBy('full_name');

    expect($rows['Known']['is_following'])->toBeTrue();
    expect($rows['Stranger']['is_following'])->toBeFalse();
});

test('a post the caller may not read does not say who liked it', function () {
    $author = User::factory()->create();
    $circle = Circle::factory()->create(['owner_id' => $author->id]);

    CircleMembership::create([
        'user_id' => $author->id,
        'circle_id' => $circle->id,
        'joined_at' => now(),
    ]);

    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
    ]);
    $circle->posts()->attach($post);

    PostLike::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

    $this->getJson(route('api.v1.posts.likes', $post))->assertForbidden();
});

test('an empty list is an empty list, not an error', function () {
    $this->getJson(route('api.v1.posts.likes', $this->post))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the list pages', function () {
    PostLike::factory()->count(3)->sequence(
        fn ($sequence) => ['created_at' => now()->subMinutes($sequence->index)]
    )->create(['post_id' => $this->post->id]);

    $first = $this->getJson(route('api.v1.posts.likes', [$this->post, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $this->getJson(route('api.v1.posts.likes', [$this->post, 'per_page' => 2, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

test('asking for an unknown post is a 404', function () {
    $this->getJson(route('api.v1.posts.likes', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});
