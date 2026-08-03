<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Rules\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot save a post', function () {
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])->assertUnauthorized();
});

test('a meditation win records the timer and counts as completed by default', function () {
    $response = $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'caption' => 'Sat with it this morning.',
        'wins' => [['type' => 'meditation', 'duration_minutes' => 20]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.caption', 'Sat with it this morning.')
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.author.id', $this->user->id)
        ->assertJsonPath('data.author.full_name', $this->user->full_name)
        ->assertJsonPath('data.wins.0.type', 'meditation')
        ->assertJsonPath('data.wins.0.duration_minutes', 20)
        ->assertJsonPath('data.wins.0.completed', true)
        ->assertJsonPath('data.wins.0.media_attached', false)
        ->assertJsonMissingPath('data.author.email');

    expect(Post::count())->toBe(1);
    expect(WinMeditation::sole()->duration_minutes)->toBe(20);
});

test('a sitting that was cut short is recorded as incomplete', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'meditation', 'duration_minutes' => 3, 'completed' => false]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.duration_minutes', 3)
        ->assertJsonPath('data.wins.0.completed', false);

    expect(WinMeditation::sole()->completed)->toBeFalse();
});

test('a learning win can be saved', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'learning',
            'learned_text' => 'Compound interest applies to habits too.',
            'reference_source' => 'https://example.com/atomic-habits']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.type', 'learning')
        ->assertJsonPath('data.wins.0.learned_text', 'Compound interest applies to habits too.')
        ->assertJsonPath('data.wins.0.reference_source', 'https://example.com/atomic-habits');

    expect(WinLearning::count())->toBe(1);
});

test('a movement win can be saved', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'caption' => 'Five easy kilometres.',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.type', 'movement')
        ->assertJsonPath('data.wins.0.movement_type', 'run');

    expect(WinMovement::sole()->movement_type)->toBe('run');
});

test('uploading files marks the win as carrying media', function () {
    $response = $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'movement_type' => 'walk',
            'media' => [
                UploadedFile::fake()->image('walk.jpg'),
                UploadedFile::fake()->create('walk.mp4', 2048, 'video/mp4'),
            ],
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.wins.0.media_attached', true)
        ->assertJsonCount(2, 'data.wins.0.media')
        ->assertJsonPath('data.wins.0.media.0.kind', 'image')
        ->assertJsonPath('data.wins.0.media.0.position', 0)
        ->assertJsonPath('data.wins.0.media.1.kind', 'video')
        ->assertJsonPath('data.wins.0.media.1.position', 1);

    expect($response->json('data.wins.0.media.0.url'))->toStartWith('http');
    expect(Media::count())->toBe(2);
    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(2);
});

test('a genuine upload survives being handed to the media library', function () {
    /*
     * A real `UploadedFile` rather than a fake one, deliberately.
     *
     * `UploadedFile::fake()` answers `getMimeType()` out of a property without
     * ever touching the disk, so it cannot notice that adding the file moves it
     * out of its temporary path and unlinks what was there. A real upload can,
     * and did: reading the type after handing the file over raised "the file
     * /private/var/tmp/... does not exist or is not readable" on every win that
     * carried a photo, with the whole suite green.
     */
    $path = tempnam(sys_get_temp_dir(), 'win').'.jpg';
    $image = imagecreatetruecolor(10, 10);
    imagejpeg($image, $path);
    imagedestroy($image);

    $win = WinMovement::factory()->create();

    $media = $win->addWinMedia(new UploadedFile($path, 'walk.jpg', 'image/jpeg', null, true));

    expect($media->mime_type)->toBe('image/jpeg')
        ->and(Storage::disk('public')->allFiles('media'))->toHaveCount(1);
});

test('files land on whichever disk the media library is pointed at', function () {
    Storage::fake('s3');
    config(['media-library.disk_name' => 's3']);

    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk',
            'media' => [UploadedFile::fake()->image('walk.jpg')]]],
    ])->assertCreated();

    // Nothing names a disk in the application itself, so pointing the library
    // somewhere else is all it takes to move where uploads are kept.
    expect(Media::sole()->disk)->toBe('s3')
        ->and(Storage::disk('s3')->allFiles('media'))->toHaveCount(1)
        ->and(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('saving a win bumps the wins count', function () {
    expect($this->user->wins_count)->toBe(0);

    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'movement', 'movement_type' => 'yoga']]])
        ->assertCreated();

    expect($this->user->refresh()->wins_count)->toBe(1);
});

test('the completed time defaults to now but can be backdated', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'movement', 'movement_type' => 'gym']]])
        ->assertCreated();

    expect(WinMovement::sole()->completed_at->isToday())->toBeTrue();

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'learning',
            'learned_text' => 'Yesterday counts too.',
            'completed_at' => now()->subDay()->toIso8601String()]],
    ])->assertCreated();

    expect(WinLearning::sole()->completed_at->isYesterday())->toBeTrue();
});

test('a win cannot be completed in the future', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run',
            'completed_at' => now()->addDay()->toIso8601String()]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.completed_at');
});

test('the win type is required and must be known', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins');

    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'napping']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins.0.type');

    expect(Post::count())->toBe(0);
});

test('a learning win needs what was learned', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'learning']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins.0.learned_text');

    expect(Post::count())->toBe(0);
});

test('a movement win accepts any movement type the client sends', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'movement', 'movement_type' => 'jousting']]])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.movement_type', 'jousting');

    expect(WinMovement::sole()->movement_type)->toBe('jousting');
});

test('a movement win can be saved without naming the movement', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'wins' => [['type' => 'movement']]])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.type', 'movement')
        ->assertJsonPath('data.wins.0.movement_type', null);

    expect(WinMovement::sole()->movement_type)->toBeNull();
});

test('an overlong movement type is rejected rather than truncated', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => str_repeat('a', 256)]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.movement_type');

    expect(Post::count())->toBe(0);
});

test('a meditation win needs a timer within range', function (int|string|null $duration) {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'meditation', 'duration_minutes' => $duration]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.duration_minutes');

    expect(Post::count())->toBe(0);
})->with(['missing' => null, 'zero' => 0, 'negative' => -5, 'too long' => 601]);

test('a url string is rejected where a file is expected', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run',
            'media' => ['https://cdn.winly.test/a.jpg']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.media.0');
});

test('an unsupported file type is rejected', function () {
    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run',
            'media' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.media.0');

    expect(Post::count())->toBe(0);
    expect(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('an oversized photo is rejected but a clip that size is fine', function () {
    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement',
            'media' => [UploadedFile::fake()->create('huge.jpg', MediaFile::MAX_IMAGE_KB + 1, 'image/jpeg')]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.media.0');

    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement',
            'media' => [UploadedFile::fake()->create('clip.mp4', MediaFile::MAX_IMAGE_KB + 1, 'video/mp4')]]],
    ])->assertCreated();
});

test('each win keeps its own files', function () {
    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'learning', 'learned_text' => 'A good chapter.',
                'media' => [UploadedFile::fake()->image('book.jpg')]],
            ['type' => 'movement', 'movement_type' => 'run',
                'media' => [UploadedFile::fake()->create('run.mp4', 512, 'video/mp4')]],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.type', 'learning')
        ->assertJsonPath('data.wins.0.media.0.kind', 'image')
        ->assertJsonPath('data.wins.1.type', 'movement')
        ->assertJsonPath('data.wins.1.media.0.kind', 'video')
        ->assertJsonCount(1, 'data.wins.0.media')
        ->assertJsonCount(1, 'data.wins.1.media');
});

test('a post with no files can still be sent as plain json', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.media_attached', false)
        ->assertJsonCount(0, 'data.wins.0.media');
});

test('deleting a post takes the win files with it', function () {
    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run',
            'media' => [UploadedFile::fake()->image('run.jpg')]]],
    ])->assertCreated();

    expect(Media::count())->toBe(1);

    Post::sole()->delete();

    expect(Media::count())->toBe(0);
});

test('a rejected win leaves no post behind', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'caption' => 'This should not survive.',
        'wins' => [['type' => 'learning']],
    ])->assertUnprocessable();

    expect(Post::count())->toBe(0);
    expect(WinLearning::count())->toBe(0);
    expect($this->user->refresh()->wins_count)->toBe(0);
});

test('posts are saved against the authenticated user, not a supplied id', function () {
    $someoneElse = User::factory()->create();

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'user_id' => $someoneElse->id,
        'wins' => [['type' => 'movement', 'movement_type' => 'swim']],
    ])->assertCreated();

    expect(Post::sole()->user_id)->toBe($this->user->id);
    expect($someoneElse->refresh()->wins_count)->toBe(0);
});

test('guests cannot read the feed', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.posts.index'))->assertUnauthorized();
});

test('the feed lists posts newest first with author and win attached', function () {
    $older = Post::factory()->create(['created_at' => now()->subHour()]);
    WinMovement::factory()->create(['post_id' => $older->id, 'movement_type' => 'run']);

    $newer = Post::factory()->create(['created_at' => now()]);
    WinMeditation::factory()->create(['post_id' => $newer->id, 'duration_minutes' => 12]);

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.wins.0.type', 'meditation')
        ->assertJsonPath('data.0.wins.0.duration_minutes', 12)
        ->assertJsonPath('data.0.author.id', $newer->user_id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.wins.0.movement_type', 'run')
        ->assertJsonMissingPath('data.0.author.email');
});

test('the feed says whether the reader already follows each author', function () {
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    Post::factory()->create(['user_id' => $followed->id, 'created_at' => now()]);
    Post::factory()->create(['user_id' => $stranger->id, 'created_at' => now()->subHour()]);

    Follow::factory()->from($this->user)->to($followed)->create();

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.author.is_following', true)
        ->assertJsonPath('data.1.author.is_following', false);
});

test('the reader never follows themselves', function () {
    Post::factory()->create(['user_id' => $this->user->id]);

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.author.is_following', false);
});

test('following a user reports the state without claiming to know the reverse', function () {
    $other = User::factory()->create();

    // The follow endpoints answer about the caller, so the nested user carries
    // no `is_following` of its own — absent rather than a guessed false.
    $this->postJson(route('api.v1.users.follow', $other))
        ->assertCreated()
        ->assertJsonPath('data.is_following', true)
        ->assertJsonMissingPath('data.user.is_following');
});

test('a post with no win detail still lists', function () {
    $post = Post::factory()->create();

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonCount(0, 'data.0.wins');
});

test('the feed runs the same number of queries however many posts it returns', function () {
    $seed = function (int $count): void {
        Post::factory()->count($count)->create()->each(function ($post) {
            $win = WinMovement::factory()->create(['post_id' => $post->id]);
            $win->addWinMedia(UploadedFile::fake()->image('walk.jpg'));
        });
    };

    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.posts.index', ['per_page' => 50]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $seed(3);
    $few = $measure();

    $seed(20);
    $many = $measure();

    // Eager loading means the cost is fixed: the post page, the authors, the
    // three win tables and their files, and the circles each post was shared
    // into. Growing the page must not add queries.
    //
    // The budget has grown three times, each time by one eager load for the
    // whole page rather than one per post: eight to nine when posts began
    // carrying their circles, nine to ten when a row began saying whether the
    // reader has saved it, ten to eleven when the authors' photos moved to the
    // media library and began arriving with them. The equality above is what
    // actually guards against the per-post kind, and it is the line that must
    // never be relaxed.
    expect($many)->toBe($few);
    expect($few)->toBeLessThanOrEqual(11);
});

test('the feed is cursor paginated', function () {
    Post::factory()->count(5)->create();

    $first = $this->getJson(route('api.v1.posts.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(route('api.v1.posts.index', ['per_page' => 2, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $firstIds = collect($first->json('data'))->pluck('id');
    $secondIds = collect($second->json('data'))->pluck('id');

    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

test('the page size is capped', function () {
    $this->getJson(route('api.v1.posts.index', ['per_page' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    $this->getJson(route('api.v1.posts.index', ['per_page' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('a post can carry all three kinds of win at once', function () {
    $response = $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'caption' => 'Did everything today.',
        'wins' => [
            ['type' => 'meditation', 'duration_minutes' => 10],
            ['type' => 'learning', 'learned_text' => 'Rest is part of the work.'],
            ['type' => 'movement', 'movement_type' => 'run'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(3, 'data.wins')
        ->assertJsonPath('data.wins.0.type', 'meditation')
        ->assertJsonPath('data.wins.0.duration_minutes', 10)
        ->assertJsonPath('data.wins.1.type', 'learning')
        ->assertJsonPath('data.wins.1.learned_text', 'Rest is part of the work.')
        ->assertJsonPath('data.wins.2.type', 'movement')
        ->assertJsonPath('data.wins.2.movement_type', 'run');

    expect(Post::count())->toBe(1);
    expect(WinMeditation::count())->toBe(1);
    expect(WinLearning::count())->toBe(1);
    expect(WinMovement::count())->toBe(1);
});

test('every win on a post counts toward the wins total', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'meditation', 'duration_minutes' => 5],
            ['type' => 'movement', 'movement_type' => 'walk'],
        ],
    ])->assertCreated();

    expect($this->user->refresh()->wins_count)->toBe(2);
});

test('the same kind of win cannot appear twice on a post', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'movement', 'movement_type' => 'run'],
            ['type' => 'movement', 'movement_type' => 'swim'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.1.type');

    expect(Post::count())->toBe(0);
});

test('a post needs at least one win', function () {
    $this->postJson(route('api.v1.posts.store'), ['visibility' => 'public', 'caption' => 'Just vibes.', 'wins' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wins');

    expect(Post::count())->toBe(0);
});

test('one bad win rejects the whole post', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'movement', 'movement_type' => 'run'],
            ['type' => 'meditation'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.1.duration_minutes');

    expect(Post::count())->toBe(0);
    expect(WinMovement::count())->toBe(0);
});

test('the feed carries every win on a post', function () {
    $post = Post::factory()->create();
    WinMeditation::factory()->create(['post_id' => $post->id, 'duration_minutes' => 12]);
    WinLearning::factory()->create(['post_id' => $post->id]);
    WinMovement::factory()->create(['post_id' => $post->id, 'movement_type' => 'cycle']);

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data.0.wins')
        ->assertJsonPath('data.0.wins.0.duration_minutes', 12)
        ->assertJsonPath('data.0.wins.2.movement_type', 'cycle');
});

test('a long reference link is stored, not truncated', function () {
    $link = 'https://example.com/'.str_repeat('a', 300);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'learning', 'learned_text' => 'x', 'reference_source' => $link]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.wins.0.reference_source', $link);

    expect(WinLearning::sole()->reference_source)->toBe($link);
});

test('an over-long reference link is a validation error, not a database error', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'learning',
            'learned_text' => 'x',
            'reference_source' => 'https://example.com/'.str_repeat('a', 2100),
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('wins.0.reference_source');

    expect(Post::count())->toBe(0);
});

test('the feed defaults to every post', function () {
    $stranger = User::factory()->create();
    Post::factory()->for($stranger)->create();
    Post::factory()->for($this->user)->create();

    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('the following feed carries only posts by people the reader follows', function () {
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    Follow::factory()->create(['follower_id' => $this->user->id, 'followee_id' => $followed->id]);

    $theirs = Post::factory()->for($followed)->create();
    Post::factory()->for($stranger)->create();
    // Your own wins are not "following": you did not choose to follow yourself,
    // and the profile is where your own posts are read.
    Post::factory()->for($this->user)->create();

    $this->getJson(route('api.v1.posts.index', ['feed' => 'following']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $theirs->id);
});

test('the circles feed carries what was shared into any circle the reader is in', function () {
    $mine = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $mine->id]);

    $theirs = Circle::factory()->create();

    $author = User::factory()->create();
    $shared = Post::factory()->for($author)->create();
    $shared->circles()->attach($mine->id);

    $elsewhere = Post::factory()->for($author)->create();
    $elsewhere->circles()->attach($theirs->id);

    // Shared with nobody in particular, so it belongs to no circle's wall.
    Post::factory()->for($author)->create();

    $this->getJson(route('api.v1.posts.index', ['feed' => 'circles']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shared->id);
});

test('a post in several of the reader\'s circles is served once', function () {
    $first = Circle::factory()->create();
    $second = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $first->id]);
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $second->id]);

    $post = Post::factory()->for(User::factory())->create();
    $post->circles()->attach([$first->id, $second->id]);

    $this->getJson(route('api.v1.posts.index', ['feed' => 'circles']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an unknown feed is rejected', function () {
    $this->getJson(route('api.v1.posts.index', ['feed' => 'everyone']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('feed');
});
