<?php

use App\Models\Meditation;
use App\Models\MeditationCategory;
use App\Models\User;
use Database\Seeders\MeditationCategorySeeder;
use Database\Seeders\MeditationSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->category = MeditationCategory::factory()->create(['name' => 'Sleep']);
});

function meditationPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => test()->category->id,
        'title' => 'Body Scan for Deep Rest',
        'description' => 'A slow pass from head to toe.',
        'thumbnail' => 'thumbnails/body-scan.jpg',
        'audio_url' => 'https://cdn.winly.test/audio/body-scan.mp3',
        'video_url' => null,
        'duration_minutes' => 22,
    ], $overrides);
}

test('guests are redirected away from the index', function () {
    auth()->logout();

    $this->get(route('meditations.index'))->assertRedirect(route('login'));
});

test('the index lists meditations with their category', function () {
    Meditation::factory()->count(3)->inCategory($this->category)->create();

    $this->get(route('meditations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('meditations/index')
            ->has('meditations.data', 3)
            ->has('meditations.data.0.category', fn (AssertableInertia $category) => $category
                ->where('name', 'Sleep')
                ->hasAll(['id', 'icon'])
            )
            ->where('totalCount', 3)
            ->has('categories', 1)
        );
});

test('the index does not run a query per row', function () {
    Meditation::factory()->count(5)->inCategory($this->category)->create();

    DB::enableQueryLog();

    $this->get(route('meditations.index'))->assertOk();

    // Paginator count, page rows, eager-loaded categories, total count, options.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6);
});

test('search matches the title', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Rain on a Quiet Roof']);
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Box Breathing']);

    $this->get(route('meditations.index', ['search' => 'rain']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('meditations.data', 1)
            ->where('meditations.data.0.title', 'Rain on a Quiet Roof')
        );
});

test('meditations can be filtered by category', function () {
    $focus = MeditationCategory::factory()->create(['name' => 'Focus']);

    Meditation::factory()->inCategory($this->category)->create();
    Meditation::factory()->inCategory($focus)->create(['title' => 'Deep Work Primer']);

    $this->get(route('meditations.index', ['category_id' => $focus->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('meditations.data', 1)
            ->where('meditations.data.0.title', 'Deep Work Primer')
        );
});

test('an unknown category filter is rejected', function () {
    $this->get(route('meditations.index', ['category_id' => 999]))->assertInvalid('category_id');
});

test('meditations can be filtered by duration range', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Quick', 'duration_minutes' => 5]);
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Long', 'duration_minutes' => 40]);

    $this->get(route('meditations.index', ['min_duration' => 10, 'max_duration' => 60]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('meditations.data', 1)
            ->where('meditations.data.0.title', 'Long')
        );
});

test('the duration range must not be inverted', function () {
    $this->get(route('meditations.index', ['min_duration' => 30, 'max_duration' => 5]))
        ->assertInvalid('max_duration');
});

test('meditations can be filtered by created date range', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Old', 'created_at' => now()->subMonth()]);
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Recent']);

    $this->get(route('meditations.index', [
        'from' => now()->subDays(2)->toDateString(),
        'to' => now()->toDateString(),
    ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('meditations.data', 1)
            ->where('meditations.data.0.title', 'Recent')
        );
});

test('meditations can be sorted by duration', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Short', 'duration_minutes' => 5]);
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Long', 'duration_minutes' => 40]);

    $this->get(route('meditations.index', ['sort' => 'duration_minutes', 'direction' => 'desc']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('meditations.data.0.title', 'Long'));
});

test('an unknown sort column is rejected', function () {
    $this->get(route('meditations.index', ['sort' => 'category_id']))->assertInvalid('sort');
});

test('a meditation can be created', function () {
    $this->from(route('meditations.index'))
        ->post(route('meditations.store'), meditationPayload(['title' => '  Body Scan  ']))
        ->assertRedirect(route('meditations.index'));

    $meditation = Meditation::sole();

    expect($meditation->title)->toBe('Body Scan');
    expect($meditation->duration_minutes)->toBe(22);
    expect($meditation->category->is($this->category))->toBeTrue();
});

test('creating requires a title, category and duration', function () {
    $this->post(route('meditations.store'), [])
        ->assertInvalid(['category_id', 'title', 'duration_minutes']);

    expect(Meditation::count())->toBe(0);
});

test('media links must be valid urls', function () {
    $this->post(route('meditations.store'), meditationPayload([
        'audio_url' => 'not-a-url',
        'video_url' => 'javascript:alert(1)',
    ]))->assertInvalid(['audio_url', 'video_url']);
});

test('the duration must be within range', function (int $duration) {
    $this->post(route('meditations.store'), meditationPayload(['duration_minutes' => $duration]))
        ->assertInvalid('duration_minutes');
})->with(['zero' => 0, 'negative' => -5, 'too long' => 601]);

test('titles must be unique within a category', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Body Scan']);

    $this->post(route('meditations.store'), meditationPayload(['title' => 'Body Scan']))
        ->assertInvalid('title');
});

test('the same title may be reused in another category', function () {
    Meditation::factory()->inCategory($this->category)->create(['title' => 'Body Scan']);
    $focus = MeditationCategory::factory()->create(['name' => 'Focus']);

    $this->post(route('meditations.store'), meditationPayload([
        'category_id' => $focus->id,
        'title' => 'Body Scan',
    ]))->assertValid();

    expect(Meditation::count())->toBe(2);
});

test('a meditation can be updated', function () {
    $meditation = Meditation::factory()->inCategory($this->category)->create();

    $this->from(route('meditations.index'))
        ->put(route('meditations.update', $meditation), meditationPayload([
            'title' => 'Renamed Session',
            'duration_minutes' => 30,
        ]))->assertRedirect(route('meditations.index'));

    expect($meditation->fresh())
        ->title->toBe('Renamed Session')
        ->duration_minutes->toBe(30);
});

test('a meditation keeps its own title when updated', function () {
    $meditation = Meditation::factory()->inCategory($this->category)->create(['title' => 'Body Scan']);

    $this->put(route('meditations.update', $meditation), meditationPayload(['title' => 'Body Scan']))
        ->assertValid();
});

test('the index carries everything the edit dialog needs', function () {
    $meditation = Meditation::factory()->inCategory($this->category)->create();

    $this->get(route('meditations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('maxDuration', 600)
            ->has('categories', 1)
            ->has('meditations.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $meditation->id)
                ->where('category_id', $meditation->category_id)
                ->where('title', $meditation->title)
                ->where('description', $meditation->description)
                ->where('thumbnail', $meditation->thumbnail)
                ->where('audio_url', $meditation->audio_url)
                ->where('video_url', $meditation->video_url)
                ->where('duration_minutes', $meditation->duration_minutes)
                ->etc()
            )
        );
});

test('a meditation can be deleted', function () {
    $meditation = Meditation::factory()->inCategory($this->category)->create();

    $this->from(route('meditations.index'))
        ->delete(route('meditations.destroy', $meditation))
        ->assertRedirect(route('meditations.index'));

    expect(Meditation::count())->toBe(0);
});

test('deleting a category deletes its meditations', function () {
    Meditation::factory()->count(2)->inCategory($this->category)->create();

    $this->delete(route('meditation-categories.destroy', $this->category));

    expect(Meditation::count())->toBe(0);
    expect(MeditationCategory::count())->toBe(0);
});

test('the seeder is idempotent', function () {
    $this->seed(MeditationCategorySeeder::class);
    $this->seed(MeditationSeeder::class);
    $this->seed(MeditationSeeder::class);

    expect(Meditation::count())->toBe(14);
});
