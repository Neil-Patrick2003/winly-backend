<?php

use App\Models\Post;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests are turned away', function () {
    app()['auth']->forgetGuards();

    $post = Post::factory()->create();

    $this->getJson(route('api.v1.posts.saved'))->assertUnauthorized();
    $this->putJson(route('api.v1.posts.save', $post))->assertUnauthorized();
    $this->deleteJson(route('api.v1.posts.unsave', $post))->assertUnauthorized();
});

test('a post can be saved, and saving it twice keeps one row', function () {
    $post = Post::factory()->create();

    $this->putJson(route('api.v1.posts.save', $post))
        ->assertCreated()
        ->assertJsonPath('data.post_id', $post->id)
        ->assertJsonPath('data.viewer_has_saved', true);

    // The second time is not a second save, and not an error either.
    $this->putJson(route('api.v1.posts.save', $post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_saved', true);

    expect(SavedPost::count())->toBe(1);
});

test('a post can be taken back off the shelf, twice over', function () {
    $post = Post::factory()->create();
    SavedPost::factory()->create(['user_id' => $this->user->id, 'post_id' => $post->id]);

    $this->deleteJson(route('api.v1.posts.unsave', $post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_saved', false);

    expect(SavedPost::count())->toBe(0);

    // Unsaving one that was never saved is treated as already done.
    $this->deleteJson(route('api.v1.posts.unsave', $post))
        ->assertOk()
        ->assertJsonPath('data.viewer_has_saved', false);
});

test('saving somebody elses post tells them nothing and counts nothing', function () {
    $post = Post::factory()->create(['likes_count' => 0]);

    $this->putJson(route('api.v1.posts.save', $post))->assertCreated();

    expect($post->refresh()->likes_count)->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0);
});

test('the list carries what you saved, most recently saved first', function () {
    $older = Post::factory()->create();
    $newer = Post::factory()->create();

    SavedPost::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $older->id,
        'created_at' => now()->subHour(),
    ]);
    SavedPost::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $newer->id,
        'created_at' => now(),
    ]);

    $this->getJson(route('api.v1.posts.saved'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        // Everything on the shelf is on it, by definition.
        ->assertJsonPath('data.0.viewer_has_saved', true);
});

test('the pile is ordered by when it was saved, not when it was written', function () {
    $old = Post::factory()->create(['created_at' => now()->subMonth()]);
    $fresh = Post::factory()->create(['created_at' => now()]);

    // The month-old post was saved last, so it sits on top.
    SavedPost::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $fresh->id,
        'created_at' => now()->subHour(),
    ]);
    SavedPost::factory()->create([
        'user_id' => $this->user->id,
        'post_id' => $old->id,
        'created_at' => now(),
    ]);

    $this->getJson(route('api.v1.posts.saved'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $old->id)
        ->assertJsonPath('data.1.id', $fresh->id);
});

test('one persons shelf is not anothers', function () {
    $mine = Post::factory()->create();
    $theirs = Post::factory()->create();

    SavedPost::factory()->create(['user_id' => $this->user->id, 'post_id' => $mine->id]);
    SavedPost::factory()->create(['post_id' => $theirs->id]);

    $this->getJson(route('api.v1.posts.saved'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('an empty shelf is not an error', function () {
    $this->getJson(route('api.v1.posts.saved'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the shelf is cursor paginated without repeating posts', function () {
    foreach (range(1, 5) as $i) {
        SavedPost::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => Post::factory()->create()->id,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $first = $this->getJson(route('api.v1.posts.saved', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(route('api.v1.posts.saved', ['per_page' => 2, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($first->json('data'))->pluck('id')
        ->intersect(collect($second->json('data'))->pluck('id')))->toBeEmpty();
});

test('listing costs the same however much is on the shelf', function () {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.posts.saved', ['per_page' => 50]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 2) as $i) {
        SavedPost::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => Post::factory()->create()->id,
        ]);
    }
    $few = $measure();

    foreach (range(1, 20) as $i) {
        SavedPost::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => Post::factory()->create()->id,
        ]);
    }

    expect($measure())->toBe($few);
});

test('the feed says which posts the reader has saved, and only theirs', function () {
    $saved = Post::factory()->create();
    $notSaved = Post::factory()->create();

    SavedPost::factory()->create(['user_id' => $this->user->id, 'post_id' => $saved->id]);
    // Somebody else's save must not fill in this reader's bookmark.
    SavedPost::factory()->create(['post_id' => $notSaved->id]);

    $rows = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'))
        ->keyBy('id');

    expect($rows[$saved->id]['viewer_has_saved'])->toBeTrue()
        ->and($rows[$notSaved->id]['viewer_has_saved'])->toBeFalse();
});

test('a saved post that is deleted takes its saves with it', function () {
    $post = Post::factory()->create();
    SavedPost::factory()->create(['user_id' => $this->user->id, 'post_id' => $post->id]);

    $post->delete();

    expect(SavedPost::count())->toBe(0);
    $this->getJson(route('api.v1.posts.saved'))->assertOk()->assertJsonCount(0, 'data');
});
