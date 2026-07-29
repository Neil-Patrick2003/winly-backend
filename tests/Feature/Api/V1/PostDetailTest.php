<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot open a post', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.posts.show', Post::factory()->create()))
        ->assertUnauthorized();
});

test('an unknown post is a 404', function () {
    $this->getJson(route('api.v1.posts.show', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});

test('a post opens with its wins and counts', function () {
    $post = Post::factory()->create(['caption' => 'Big day.']);
    WinMeditation::factory()->create(['post_id' => $post->id, 'duration_minutes' => 12]);
    WinMovement::factory()->create(['post_id' => $post->id, 'movement_type' => 'run']);

    $this->putJson(route('api.v1.posts.like', $post))->assertCreated();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Well done.'])->assertCreated();

    $this->getJson(route('api.v1.posts.show', $post))
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.caption', 'Big day.')
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.viewer_has_liked', true)
        ->assertJsonPath('data.comments_count', 1)
        ->assertJsonCount(2, 'data.wins')
        ->assertJsonPath('data.wins.0.duration_minutes', 12)
        ->assertJsonPath('data.wins.1.movement_type', 'run')
        ->assertJsonPath('data.author.id', $post->user_id)
        ->assertJsonMissingPath('data.comments');
});

test('the post view never carries comments, only the count', function () {
    $post = Post::factory()->create();

    foreach (range(1, 4) as $i) {
        $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => "c{$i}"])->assertCreated();
    }

    $this->getJson(route('api.v1.posts.show', $post))
        ->assertOk()
        ->assertJsonPath('data.comments_count', 4)
        ->assertJsonMissingPath('data.comments');
});

test('the feed leaves comments out too', function () {
    $post = Post::factory()->create();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Nice.'])->assertCreated();

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.comments_count', 1)
        ->assertJsonMissingPath('data.0.comments');
});

test('another persons like does not show as the callers', function () {
    $post = Post::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->putJson(route('api.v1.posts.like', $post))->assertCreated();

    Sanctum::actingAs($this->user);

    $this->getJson(route('api.v1.posts.show', $post))
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.viewer_has_liked', false);
});

test('opening a post costs the same however many comments it has', function () {
    $measure = function (Post $post): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.posts.show', $post))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = Post::factory()->create();
    Comment::factory()->count(2)->create(['post_id' => $few->id]);

    $many = Post::factory()->create();
    Comment::factory()->count(25)->create(['post_id' => $many->id]);

    expect($measure($many))->toBe($measure($few));
});
