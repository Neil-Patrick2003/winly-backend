<?php

use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot like a post', function () {
    app()['auth']->forgetGuards();

    $this->putJson(route('api.v1.posts.like', $this->post))->assertUnauthorized();
});

test('a user can like a post', function () {
    $this->putJson(route('api.v1.posts.like', $this->post))
        ->assertCreated()
        ->assertJsonPath('data.post_id', $this->post->id)
        ->assertJsonPath('data.viewer_has_liked', true)
        ->assertJsonPath('data.likes_count', 1);

    expect(PostLike::count())->toBe(1);
    expect($this->post->refresh()->likes_count)->toBe(1);
});

test('liking twice does not count twice', function () {
    $this->putJson(route('api.v1.posts.like', $this->post))->assertCreated();

    $this->putJson(route('api.v1.posts.like', $this->post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_liked', true)
        ->assertJsonPath('data.likes_count', 1);

    expect(PostLike::count())->toBe(1);
    expect($this->post->refresh()->likes_count)->toBe(1);
});

test('a user can take a like back', function () {
    $this->putJson(route('api.v1.posts.like', $this->post))->assertCreated();

    $this->deleteJson(route('api.v1.posts.unlike', $this->post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_liked', false)
        ->assertJsonPath('data.likes_count', 0);

    expect(PostLike::count())->toBe(0);
    expect($this->post->refresh()->likes_count)->toBe(0);
});

test('unliking a post that was never liked is not an error', function () {
    $this->deleteJson(route('api.v1.posts.unlike', $this->post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_liked', false)
        ->assertJsonPath('data.likes_count', 0);

    expect($this->post->refresh()->likes_count)->toBe(0);
});

test('one person liking does not mark it liked for everybody', function () {
    $this->putJson(route('api.v1.posts.like', $this->post))->assertCreated();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.likes_count', 1)
        ->assertJsonPath('data.0.viewer_has_liked', false);
});

test('the feed reports whether the caller liked each post', function () {
    $liked = Post::factory()->create();
    $this->putJson(route('api.v1.posts.like', $liked))->assertCreated();

    $response = $this->getJson(route('api.v1.posts.index'))->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows[$liked->id]['viewer_has_liked'])->toBeTrue();
    expect($rows[$this->post->id]['viewer_has_liked'])->toBeFalse();
});

test('likes from different people all count', function () {
    $this->putJson(route('api.v1.posts.like', $this->post))->assertCreated();

    Sanctum::actingAs(User::factory()->create());

    $this->putJson(route('api.v1.posts.like', $this->post))
        ->assertCreated()
        ->assertJsonPath('data.likes_count', 2);

    expect(PostLike::count())->toBe(2);
});

test('liking an unknown post is a 404', function () {
    $this->putJson(route('api.v1.posts.like', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});
