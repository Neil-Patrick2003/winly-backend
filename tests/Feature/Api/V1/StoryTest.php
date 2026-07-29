<?php

use App\Models\Story;
use App\Models\User;
use App\Models\WinMedia;
use Illuminate\Http\UploadedFile;
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
    expect(Storage::disk('public')->allFiles('stories'))->toHaveCount(1);
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
        'image' => UploadedFile::fake()->create('huge.jpg', WinMedia::MAX_IMAGE_KB + 1, 'image/jpeg'),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');
});

test('a person can take their own story down', function () {
    $story = Story::factory()->create(['user_id' => $this->user->id]);

    $this->deleteJson(route('api.v1.stories.destroy', $story))
        ->assertOk()
        ->assertJsonPath('data.id', $story->id);

    expect(Story::count())->toBe(0);
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
