<?php

use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Rules\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/**
 * A post created the way a client would, so its media is real stored files
 * rather than factory rows pointing at a CDN that was never written to.
 *
 * @param  list<UploadedFile>  $media
 */
function postWithMovement(array $media = []): Post
{
    test()->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'caption' => 'Went out for a walk.',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk', 'media' => $media]],
    ])->assertCreated();

    return Post::sole();
}

test('guests cannot edit or delete a post', function () {
    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create();

    app()['auth']->forgetGuards();

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement']],
    ])->assertUnauthorized();

    $this->deleteJson(route('api.v1.posts.destroy', $post))->assertUnauthorized();
});

test('a post answers only to the person who wrote it', function () {
    $theirs = Post::factory()->for(User::factory())->create();
    WinMovement::factory()->for($theirs, 'post')->create();

    $this->patchJson(route('api.v1.posts.update', $theirs), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'sprint']],
    ])->assertForbidden();

    $this->deleteJson(route('api.v1.posts.destroy', $theirs))->assertForbidden();

    expect($theirs->fresh())->not->toBeNull()
        ->and(WinMovement::sole()->movement_type)->not->toBe('sprint');
});

test('editing rewrites the caption and the win it carries', function () {
    $post = Post::factory()->for($this->user)->create(['caption' => 'Read a chapter.']);
    WinLearning::factory()->for($post, 'post')->create([
        'learned_text' => 'Teh compiler is the boss.',
    ]);

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'caption' => 'Read two chapters.',
        'wins' => [[
            'type' => 'learning',
            'learned_text' => 'The compiler is the boss.',
            'reference_source' => 'Crafting Interpreters',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.caption', 'Read two chapters.')
        ->assertJsonPath('data.wins.0.learned_text', 'The compiler is the boss.')
        ->assertJsonPath('data.wins.0.reference_source', 'Crafting Interpreters');

    // Updated in place rather than replaced, so likes and comments that point
    // at the win are still pointing at something.
    expect(WinLearning::count())->toBe(1);
});

test('a caption can be cleared, and left alone by saying nothing about it', function () {
    $post = Post::factory()->for($this->user)->create(['caption' => 'Something I wrote.']);
    WinMovement::factory()->for($post, 'post')->create();

    // An empty string arrives as null, by way of `ConvertEmptyStringsToNull`.
    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'caption' => '',
        'wins' => [['type' => 'movement']],
    ])
        ->assertOk()
        ->assertJsonPath('data.caption', null);

    $post->update(['caption' => 'Back again.']);

    // Not mentioned at all, which is a different thing from cleared.
    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement']],
    ])
        ->assertOk()
        ->assertJsonPath('data.caption', 'Back again.');
});

test('a kind left out of the edit is dropped, and its files with it', function () {
    $post = Post::factory()->for($this->user)->create();
    WinLearning::factory()->for($post, 'post')->create(['learned_text' => 'Something.']);
    $movement = WinMovement::factory()->for($post, 'post')->create();
    $movement->addWinMedia(UploadedFile::fake()->image('walk.jpg'));

    // Only the learning is named, so the movement is being removed.
    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'learning', 'learned_text' => 'Something.']],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.wins')
        ->assertJsonPath('data.wins.0.type', 'learning');

    expect(WinMovement::query()->where('post_id', $post->id)->count())->toBe(0)
        // The morph cannot carry a foreign key, so nothing would have cleaned
        // these up on their own.
        ->and(Media::query()->where('model_id', $movement->id)->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('a kind that was not there can be added', function () {
    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create(['movement_type' => 'walk']);

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'movement', 'movement_type' => 'walk'],
            ['type' => 'meditation', 'duration_minutes' => 15],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.wins');

    expect(WinMeditation::sole()->duration_minutes)->toBe(15);
});

test('the last win cannot be edited away', function () {
    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create();

    $this->patchJson(route('api.v1.posts.update', $post), ['visibility' => 'public', 'wins' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins');

    expect(WinMovement::count())->toBe(1);
});

test('named media is dropped, its file deleted, and the rest renumbered', function () {
    $post = postWithMovement([
        UploadedFile::fake()->image('one.jpg'),
        UploadedFile::fake()->image('two.jpg'),
        UploadedFile::fake()->image('three.jpg'),
    ]);

    $media = Media::query()->orderBy('order_column')->get();
    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(3);

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'movement_type' => 'walk',
            // The middle one, so the gap it leaves has to be closed.
            'remove_media_ids' => [$media[1]->uuid],
        ]],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.wins.0.media')
        ->assertJsonPath('data.wins.0.media.0.position', 0)
        ->assertJsonPath('data.wins.0.media.1.position', 1);

    expect(Media::count())->toBe(2)
        ->and(Storage::disk('public')->allFiles('media'))->toHaveCount(2);
});

test('new files are added after the ones already there', function () {
    $post = postWithMovement([UploadedFile::fake()->image('first.jpg')]);

    $this->patch(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'movement_type' => 'walk',
            'media' => [UploadedFile::fake()->image('second.jpg')],
        ]],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'data.wins.0.media')
        ->assertJsonPath('data.wins.0.media.1.position', 1);

    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(2);
});

test('media belonging to another post cannot be removed through this one', function () {
    $mine = Post::factory()->for($this->user)->create();
    $myWin = WinMovement::factory()->for($mine, 'post')->create();

    $theirs = Post::factory()->for(User::factory())->create();
    $theirWin = WinMovement::factory()->for($theirs, 'post')->create();
    $theirMedia = $theirWin->addWinMedia(UploadedFile::fake()->image('theirs.jpg'));

    $this->patchJson(route('api.v1.posts.update', $mine), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'remove_media_ids' => [$theirMedia->uuid],
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins.0.remove_media_ids.0');

    expect($theirMedia->fresh())->not->toBeNull()
        ->and($myWin->fresh())->not->toBeNull();
});

test('the media cap counts what the win is already holding', function () {
    $post = Post::factory()->for($this->user)->create();
    $win = WinMovement::factory()->for($post, 'post')->create();

    foreach (range(1, MediaFile::MAX_PER_WIN) as $number) {
        $win->addWinMedia(UploadedFile::fake()->image("full-{$number}.jpg"));
    }

    $this->patch(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'media' => [UploadedFile::fake()->image('one-too-many.jpg')],
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins.0.media');

    expect(Media::count())->toBe(MediaFile::MAX_PER_WIN);
});

test('deleting a post takes its rows and its files with it', function () {
    $post = postWithMovement([UploadedFile::fake()->image('walk.jpg')]);

    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(1);

    $this->deleteJson(route('api.v1.posts.destroy', $post))
        ->assertOk()
        ->assertJsonPath('data.id', $post->id);

    expect(Post::count())->toBe(0)
        ->and(WinMovement::count())->toBe(0)
        ->and(Media::count())->toBe(0)
        // The database cascade fires no model events, so this is the part that
        // only happens because the controller went looking for the files.
        ->and(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('deleting the only win of today takes the streak back down', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00'));

    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create([
        'completed_at' => Carbon::parse('2026-07-29 08:00:00'),
    ]);

    $this->user->forceFill([
        'wins_count' => 1,
        'streak_days' => 1,
        'longest_streak' => 1,
        'last_win_on' => Carbon::parse('2026-07-29'),
    ])->save();

    $this->deleteJson(route('api.v1.posts.destroy', $post))->assertOk();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(0)
        ->and($this->user->last_win_on)->toBeNull()
        ->and($this->user->wins_count)->toBe(0)
        ->and($this->user->currentStreak())->toBe(0);
});

test('moving a win to another day rebuilds the streak around it', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00'));

    // Two days running: Tuesday and Wednesday.
    $posts = collect(['2026-07-28', '2026-07-29'])->map(function (string $day): Post {
        $post = Post::factory()->for($this->user)->create();
        WinMovement::factory()->for($post, 'post')->create([
            'completed_at' => Carbon::parse("{$day} 08:00:00"),
        ]);

        return $post;
    });

    $wednesday = $posts->last();

    // Pull Wednesday's win back to Sunday, breaking the run.
    $this->patchJson(route('api.v1.posts.update', $wednesday), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'completed_at' => '2026-07-26 08:00:00',
        ]],
    ])->assertOk();

    $this->user->refresh();

    // Sunday and Tuesday with Monday missing: the run ending at the last win
    // is Tuesday alone.
    expect($this->user->last_win_on?->toDateString())->toBe('2026-07-28')
        ->and($this->user->streak_days)->toBe(1);
});

test('an edit never costs someone their longest streak', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00'));

    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create([
        'completed_at' => Carbon::parse('2026-07-29 08:00:00'),
    ]);

    // A run from long ago, whose posts are no longer around to prove it.
    $this->user->forceFill(['longest_streak' => 11])->save();

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk']],
    ])->assertOk();

    expect($this->user->fresh()->longest_streak)->toBe(11);
});

test('wins_count follows the wins on the post', function () {
    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create();
    WinLearning::factory()->for($post, 'post')->create(['learned_text' => 'A thing.']);

    $this->patchJson(route('api.v1.posts.update', $post), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk']],
    ])->assertOk();

    expect($this->user->fresh()->wins_count)->toBe(1);
});
