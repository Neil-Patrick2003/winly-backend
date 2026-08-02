<?php

use App\Models\Follow;
use App\Models\Story;
use App\Models\StoryReaction;
use App\Models\StoryView;
use App\Models\User;
use App\Rules\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot post or delete a story', function () {
    $story = Story::factory()->create();
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.stories.store'), ['image' => UploadedFile::fake()->image('a.jpg')])
        ->assertUnauthorized();
    $this->deleteJson(route('api.v1.stories.destroy', $story))->assertUnauthorized();
});

test('a story can be shared', function () {
    $response = $this->post(route('api.v1.stories.store'), [
        'image' => UploadedFile::fake()->image('sunrise.jpg', 1080, 1920),
        'caption' => 'Morning light.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.caption', 'Morning light.')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'image_url', 'caption', 'expires_at', 'is_active', 'created_at']]);

    expect($response->json('data.image_url'))->toStartWith('http');
    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(1);
    expect(Story::sole()->user_id)->toBe($this->user->id);
});

test('the caption is optional', function () {
    $this->post(route('api.v1.stories.store'), ['image' => UploadedFile::fake()->image('a.jpg')])
        ->assertCreated()
        ->assertJsonPath('data.caption', null);
});

test('a story expires a day after it is shared', function () {
    $this->freezeTime();

    $this->post(route('api.v1.stories.store'), ['image' => UploadedFile::fake()->image('a.jpg')])
        ->assertCreated();

    expect(Story::sole()->expires_at->toDateTimeString())
        ->toBe(now()->addHours(Story::LIFETIME_HOURS)->toDateTimeString());
});

test('the expiry cannot be dictated by the caller', function () {
    $this->freezeTime();

    $this->post(route('api.v1.stories.store'), [
        'image' => UploadedFile::fake()->image('a.jpg'),
        'expires_at' => now()->addYear()->toIso8601String(),
    ])->assertCreated();

    expect(Story::sole()->expires_at->toDateTimeString())
        ->toBe(now()->addHours(Story::LIFETIME_HOURS)->toDateTimeString());
});

test('a photo is required', function () {
    $this->postJson(route('api.v1.stories.store'), ['caption' => 'No photo.'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Story::count())->toBe(0);
});

test('a video is rejected', function () {
    $this->post(route('api.v1.stories.store'), [
        'image' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles('stories'))->toBeEmpty();
});

test('an oversized photo is rejected', function () {
    $this->post(route('api.v1.stories.store'), [
        'image' => UploadedFile::fake()->create('huge.jpg', MediaFile::MAX_IMAGE_KB + 1, 'image/jpeg'),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');
});

test('a person can take their own story down, and its photo with it', function () {
    $story = Story::factory()->create(['user_id' => $this->user->id]);
    $story->addMedia(UploadedFile::fake()->image('sunset.jpg'))->toMediaCollection(Story::IMAGE_COLLECTION);

    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(1);

    $this->deleteJson(route('api.v1.stories.destroy', $story))
        ->assertOk()
        ->assertJsonPath('data.id', $story->id);

    // The file used to be left behind: the row went and nothing ever went
    // looking for the bytes it named.
    expect(Story::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('a story cannot be taken down by anybody else', function () {
    $story = Story::factory()->create();

    $this->deleteJson(route('api.v1.stories.destroy', $story))->assertForbidden();

    expect(Story::count())->toBe(1);
});

test('an unknown story is a 404', function () {
    $this->deleteJson(route('api.v1.stories.destroy', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});

test('sharing a story flips the callers own flag', function () {
    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', false);

    $this->post(route('api.v1.stories.store'), ['image' => UploadedFile::fake()->image('a.jpg')])
        ->assertCreated();

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', true);
});

test('an expired story does not keep the flag up', function () {
    Story::factory()->expired()->create(['user_id' => $this->user->id]);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', false);
});

test('the story list carries your own and the people you follow, grouped by poster', function () {
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    Follow::factory()->from($this->user)->to($followed)->create();

    Story::factory()->count(2)->for($followed)->create();
    Story::factory()->for($this->user)->create();
    Story::factory()->for($stranger)->create();

    $response = $this->getJson(route('api.v1.stories.index'))->assertOk();

    $authors = collect($response->json('data'))->pluck('author.id');

    expect($authors)->toHaveCount(2)
        ->and($authors)->not->toContain($stranger->id)
        // The reader's own reel leads.
        ->and($authors->first())->toBe($this->user->id);

    $theirs = collect($response->json('data'))->firstWhere('author.id', $followed->id);
    expect($theirs['stories'])->toHaveCount(2);
});

test('expired stories are left out of the list', function () {
    Story::factory()->for($this->user)->create(['expires_at' => now()->subHour()]);

    $this->getJson(route('api.v1.stories.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a reel is unseen until every story in it has been watched', function () {
    $followed = User::factory()->create();
    Follow::factory()->from($this->user)->to($followed)->create();
    $stories = Story::factory()->count(2)->for($followed)->create();

    $this->getJson(route('api.v1.stories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.has_unseen', true)
        ->assertJsonPath('data.0.stories.0.viewed', false);

    $this->postJson(route('api.v1.stories.view', $stories->first()))->assertOk();

    // One watched, one not: still something new to see.
    $this->getJson(route('api.v1.stories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.has_unseen', true);

    $this->postJson(route('api.v1.stories.view', $stories->last()))->assertOk();

    $this->getJson(route('api.v1.stories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.has_unseen', false)
        ->assertJsonPath('data.0.stories.0.viewed', true);
});

test('watching the same story twice counts once', function () {
    $story = Story::factory()->create();

    $this->postJson(route('api.v1.stories.view', $story))->assertOk();
    $this->postJson(route('api.v1.stories.view', $story))
        ->assertOk()
        ->assertJsonPath('data.views_count', 1);

    expect(StoryView::count())->toBe(1);
});

test('watching your own story is not a view', function () {
    $story = Story::factory()->for($this->user)->create();

    $this->postJson(route('api.v1.stories.view', $story))
        ->assertOk()
        ->assertJsonPath('data.views_count', 0);

    expect(StoryView::count())->toBe(0);
});

test('the story list runs the same number of queries however many stories there are', function () {
    $followed = User::factory()->count(3)->create();
    foreach ($followed as $person) {
        Follow::factory()->from($this->user)->to($person)->create();
    }

    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.stories.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    Story::factory()->for($followed->first())->create();
    $few = $measure();

    foreach ($followed as $person) {
        Story::factory()->count(4)->for($person)->create();
    }
    $many = $measure();

    expect($many)->toBe($few);
});

test('the poster can see who watched their story, most recent first', function () {
    $story = Story::factory()->for($this->user)->create();
    $early = User::factory()->create(['full_name' => 'Early Bird']);
    $late = User::factory()->create(['full_name' => 'Late Riser']);

    StoryView::factory()->for($story)->create([
        'viewer_id' => $early->id,
        'viewed_at' => now()->subHours(2),
    ]);
    StoryView::factory()->for($story)->create([
        'viewer_id' => $late->id,
        'viewed_at' => now()->subMinute(),
    ]);

    $this->getJson(route('api.v1.stories.viewers', $story))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.full_name', 'Late Riser')
        ->assertJsonPath('data.1.full_name', 'Early Bird')
        ->assertJsonStructure(['data' => [['id', 'full_name', 'avatar_url', 'viewed_at']]]);
});

test('nobody else can see who watched a story', function () {
    $story = Story::factory()->create();

    $this->getJson(route('api.v1.stories.viewers', $story))->assertForbidden();
});

test('the view count is shown to the poster and hidden from everyone else', function () {
    $mine = Story::factory()->for($this->user)->create();
    $watcher = User::factory()->create();
    StoryView::factory()->for($mine)->create(['viewer_id' => $watcher->id]);

    $followed = User::factory()->create();
    Follow::factory()->from($this->user)->to($followed)->create();
    Story::factory()->for($followed)->create();

    $response = $this->getJson(route('api.v1.stories.index'))->assertOk();

    $reels = collect($response->json('data'));
    $own = $reels->firstWhere('author.id', $this->user->id);
    $theirs = $reels->firstWhere('author.id', $followed->id);

    expect($own['stories'][0]['views_count'])->toBe(1)
        ->and($theirs['stories'][0])->not->toHaveKey('views_count');
});

test('a story can be reacted to, changed, and taken back', function () {
    $story = Story::factory()->create();

    $this->putJson(route('api.v1.stories.react', $story), ['reaction_type' => 'love'])
        ->assertCreated()
        ->assertJsonPath('data.viewer_reaction', 'love')
        ->assertJsonPath('data.reactions_count', 1);

    // Changing it replaces rather than adds — one person, one reaction.
    $this->putJson(route('api.v1.stories.react', $story), ['reaction_type' => 'celebrate'])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', 'celebrate')
        ->assertJsonPath('data.reactions_count', 1);

    expect(StoryReaction::count())->toBe(1);

    $this->deleteJson(route('api.v1.stories.unreact', $story))
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', null)
        ->assertJsonPath('data.reactions_count', 0);

    expect(StoryReaction::count())->toBe(0);
});

test('an invented reaction is rejected', function () {
    $story = Story::factory()->create();

    $this->putJson(route('api.v1.stories.react', $story), ['reaction_type' => 'shrug'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reaction_type');
});

test('the story list carries the readers own reaction', function () {
    $followed = User::factory()->create();
    Follow::factory()->from($this->user)->to($followed)->create();
    $story = Story::factory()->for($followed)->create();

    StoryReaction::factory()->for($story)->create([
        'user_id' => $this->user->id,
        'reaction_type' => 'support',
    ]);
    // Somebody else's reaction must not be mistaken for the reader's.
    StoryReaction::factory()->for($story)->create(['reaction_type' => 'love']);

    $this->getJson(route('api.v1.stories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.stories.0.viewer_reaction', 'support');
});

test('a follow list says whether each persons story is still unwatched', function () {
    $unwatched = User::factory()->create();
    $watched = User::factory()->create();
    Follow::factory()->from($this->user)->to($unwatched)->create();
    Follow::factory()->from($this->user)->to($watched)->create();

    Story::factory()->for($unwatched)->create();
    $seen = Story::factory()->for($watched)->create();
    StoryView::factory()->for($seen)->create(['viewer_id' => $this->user->id]);

    $people = collect(
        $this->getJson(route('api.v1.users.following', $this->user))->assertOk()->json('data')
    );

    expect($people->firstWhere('id', $unwatched->id)['has_unseen_story'])->toBeTrue()
        ->and($people->firstWhere('id', $watched->id)['has_unseen_story'])->toBeFalse()
        ->and($people->firstWhere('id', $watched->id)['has_active_story'])->toBeTrue();
});

test('the viewers list says what each of them left, if anything', function () {
    $story = Story::factory()->for($this->user)->create();
    $reacted = User::factory()->create(['full_name' => 'Reacted Rita']);
    $justWatched = User::factory()->create(['full_name' => 'Quiet Quinn']);

    StoryView::factory()->for($story)->create([
        'viewer_id' => $reacted->id,
        'viewed_at' => now()->subMinute(),
    ]);
    StoryReaction::factory()->for($story)->create([
        'user_id' => $reacted->id,
        'reaction_type' => 'celebrate',
    ]);
    StoryView::factory()->for($story)->create([
        'viewer_id' => $justWatched->id,
        'viewed_at' => now()->subHour(),
    ]);

    // A reaction on a different story must not leak onto this one's viewers.
    StoryReaction::factory()->create(['user_id' => $justWatched->id, 'reaction_type' => 'love']);

    $viewers = collect($this->getJson(route('api.v1.stories.viewers', $story))->assertOk()->json('data'));

    expect($viewers->firstWhere('id', $reacted->id)['reaction_type'])->toBe('celebrate')
        ->and($viewers->firstWhere('id', $justWatched->id)['reaction_type'])->toBeNull();
});

test('the poster is told which reactions came in, and nobody else is', function () {
    $mine = Story::factory()->for($this->user)->create();
    StoryReaction::factory()->count(2)->for($mine)->create(['reaction_type' => 'love']);
    StoryReaction::factory()->for($mine)->create(['reaction_type' => 'like']);

    $followed = User::factory()->create();
    Follow::factory()->from($this->user)->to($followed)->create();
    $theirs = Story::factory()->for($followed)->create();
    StoryReaction::factory()->for($theirs)->create(['reaction_type' => 'support']);

    $reels = collect($this->getJson(route('api.v1.stories.index'))->assertOk()->json('data'));

    $own = $reels->firstWhere('author.id', $this->user->id)['stories'][0];
    $other = $reels->firstWhere('author.id', $followed->id)['stories'][0];

    // Most common first, and distinct kinds rather than one entry per person.
    expect($own['reaction_types'])->toBe(['love', 'like'])
        ->and($own['reactions_count'])->toBe(3)
        ->and($other)->not->toHaveKey('reaction_types')
        ->and($other)->not->toHaveKey('reactions_count');
});
