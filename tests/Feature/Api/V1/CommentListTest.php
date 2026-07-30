<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot list comments', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.posts.comments.index', $this->post))->assertUnauthorized();
});

test('comments list newest first with their authors', function () {
    foreach (['first', 'second', 'third'] as $index => $text) {
        Comment::factory()->create([
            'post_id' => $this->post->id,
            'text' => $text,
            'created_at' => now()->subMinutes(10 - $index),
        ]);
    }

    $this->getJson(route('api.v1.posts.comments.index', $this->post))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.text', 'third')
        ->assertJsonPath('data.1.text', 'second')
        ->assertJsonPath('data.2.text', 'first')
        ->assertJsonPath('data.0.post_id', $this->post->id)
        ->assertJsonStructure(['data' => [['id', 'post_id', 'text', 'created_at', 'updated_at', 'author']]])
        ->assertJsonMissingPath('data.0.author.email');
});

test('a comment left mid scroll does not disturb the page being read', function () {
    foreach (range(1, 4) as $index) {
        Comment::factory()->create([
            'post_id' => $this->post->id,
            'text' => "comment {$index}",
            'created_at' => now()->subMinutes(10 - $index),
        ]);
    }

    $first = $this->getJson(route('api.v1.posts.comments.index', ['post' => $this->post, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.0.text', 'comment 4');

    Comment::factory()->create([
        'post_id' => $this->post->id,
        'text' => 'arrived while reading',
        'created_at' => now(),
    ]);

    $second = $this->getJson(route('api.v1.posts.comments.index', [
        'post' => $this->post,
        'per_page' => 2,
        'cursor' => $first->json('meta.next_cursor'),
    ]))->assertOk();

    expect(collect($second->json('data'))->pluck('text')->all())
        ->toBe(['comment 2', 'comment 1']);
});

test('paging walks back through the thread newest to oldest', function () {
    foreach (range(1, 5) as $index) {
        Comment::factory()->create([
            'post_id' => $this->post->id,
            'text' => "comment {$index}",
            'created_at' => now()->subMinutes(10 - $index),
        ]);
    }

    $seen = collect();
    $cursor = null;

    do {
        $page = $this->getJson(route('api.v1.posts.comments.index', array_filter([
            'post' => $this->post->id,
            'per_page' => 2,
            'cursor' => $cursor,
        ])))->assertOk();

        $seen = $seen->concat(collect($page->json('data'))->pluck('text'));
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null);

    expect($seen->all())->toBe([
        'comment 5', 'comment 4', 'comment 3', 'comment 2', 'comment 1',
    ]);
});

test('a post with no comments returns an empty page', function () {
    $this->getJson(route('api.v1.posts.comments.index', $this->post))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('comments from another post are not included', function () {
    Comment::factory()->create(['post_id' => $this->post->id, 'text' => 'mine']);
    Comment::factory()->create(['post_id' => Post::factory()->create()->id, 'text' => 'theirs']);

    $this->getJson(route('api.v1.posts.comments.index', $this->post))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.text', 'mine');
});

test('the list is cursor paginated without repeating rows', function () {
    Comment::factory()->count(5)->create(['post_id' => $this->post->id]);

    $first = $this->getJson(route('api.v1.posts.comments.index', ['post' => $this->post, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(route('api.v1.posts.comments.index', ['post' => $this->post, 'per_page' => 2, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $firstIds = collect($first->json('data'))->pluck('id');
    $secondIds = collect($second->json('data'))->pluck('id');

    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

test('paging walks the whole thread exactly once', function () {
    Comment::factory()->count(7)->create(['post_id' => $this->post->id]);

    $seen = collect();
    $cursor = null;

    do {
        $page = $this->getJson(route('api.v1.posts.comments.index', array_filter([
            'post' => $this->post->id,
            'per_page' => 3,
            'cursor' => $cursor,
        ])))->assertOk();

        $seen = $seen->concat(collect($page->json('data'))->pluck('id'));
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null);

    expect($seen)->toHaveCount(7);
    expect($seen->unique())->toHaveCount(7);
});

test('the page size is capped', function () {
    $this->getJson(route('api.v1.posts.comments.index', ['post' => $this->post, 'per_page' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('an unknown post is a 404', function () {
    $this->getJson(route('api.v1.posts.comments.index', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});

test('listing runs the same queries however many comments there are', function () {
    $measure = function (Post $post): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.posts.comments.index', ['post' => $post, 'per_page' => 50]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    Comment::factory()->count(2)->create(['post_id' => $this->post->id]);
    $few = $measure($this->post);

    Comment::factory()->count(25)->create(['post_id' => $this->post->id]);
    expect($measure($this->post))->toBe($few);
});
