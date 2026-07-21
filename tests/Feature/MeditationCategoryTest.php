<?php

use App\Models\Meditation;
use App\Models\MeditationCategory;
use App\Models\User;
use Database\Seeders\MeditationCategorySeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests are redirected away from the index', function () {
    auth()->logout();

    $this->get(route('meditation-categories.index'))->assertRedirect(route('login'));
});

test('the index lists categories', function () {
    MeditationCategory::factory()->count(3)->create();

    $this->get(route('meditation-categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('meditation-categories/index')
            ->has('categories.data', 3)
            ->where('totalCount', 3)
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
        );
});

test('the index paginates using the requested page size', function () {
    MeditationCategory::factory()->count(12)->create();

    $this->get(route('meditation-categories.index', ['per_page' => 10]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories.data', 10)
            ->where('categories.total', 12)
            ->where('categories.last_page', 2)
        );
});

test('search matches the name', function () {
    MeditationCategory::factory()->create(['name' => 'Deep Sleep']);
    MeditationCategory::factory()->create(['name' => 'Focus']);

    $this->get(route('meditation-categories.index', ['search' => 'sleep']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Deep Sleep')
        );
});

test('search matches the description', function () {
    MeditationCategory::factory()->create(['name' => 'Focus', 'description' => 'Settle a racing mind.']);
    MeditationCategory::factory()->create(['name' => 'Sleep', 'description' => 'Wind down for the night.']);

    $this->get(route('meditation-categories.index', ['search' => 'racing']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Focus')
        );
});

test('search treats wildcards as literal characters', function () {
    MeditationCategory::factory()->create(['name' => 'Focus']);

    $this->get(route('meditation-categories.index', ['search' => '%']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('categories.data', 0));
});

test('categories can be sorted', function () {
    MeditationCategory::factory()->create(['name' => 'Alpha']);
    MeditationCategory::factory()->create(['name' => 'Zulu']);

    $this->get(route('meditation-categories.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.data.0.name', 'Zulu')
        );
});

test('an unknown sort column is rejected', function () {
    $this->get(route('meditation-categories.index', ['sort' => 'password']))
        ->assertInvalid('sort');
});

test('categories can be filtered by created date range', function () {
    MeditationCategory::factory()->create(['name' => 'Old', 'created_at' => now()->subMonth()]);
    MeditationCategory::factory()->create(['name' => 'Recent', 'created_at' => now()]);

    $this->get(route('meditation-categories.index', [
        'from' => now()->subDays(2)->toDateString(),
        'to' => now()->toDateString(),
    ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Recent')
        );
});

test('the date range must not be inverted', function () {
    $this->get(route('meditation-categories.index', [
        'from' => now()->toDateString(),
        'to' => now()->subWeek()->toDateString(),
    ]))->assertInvalid('to');
});

test('an unsupported page size is rejected', function () {
    $this->get(route('meditation-categories.index', ['per_page' => 500]))->assertInvalid('per_page');
});

test('a category can be created', function () {
    $this->from(route('meditation-categories.index'))
        ->post(route('meditation-categories.store'), [
            'name' => '  Breathwork  ',
            'icon' => 'wind',
            'description' => 'Paced breathing patterns.',
        ])->assertRedirect(route('meditation-categories.index'));

    $category = MeditationCategory::sole();

    expect($category->name)->toBe('Breathwork');
    expect($category->icon)->toBe('wind');
    expect($category->description)->toBe('Paced breathing patterns.');
});

test('a category can be created without a description', function () {
    $this->post(route('meditation-categories.store'), [
        'name' => 'Focus',
        'icon' => 'brain',
        'description' => null,
    ])->assertRedirect();

    expect(MeditationCategory::sole()->description)->toBeNull();
});

test('creating requires a name and a known icon', function () {
    $this->post(route('meditation-categories.store'), ['name' => '', 'icon' => 'not-an-icon'])
        ->assertInvalid(['name', 'icon']);

    expect(MeditationCategory::count())->toBe(0);
});

test('category names must be unique', function () {
    MeditationCategory::factory()->create(['name' => 'Sleep']);

    $this->post(route('meditation-categories.store'), ['name' => 'Sleep', 'icon' => 'moon'])
        ->assertInvalid('name');
});

test('a category can be updated', function () {
    $category = MeditationCategory::factory()->create(['name' => 'Sleep', 'icon' => 'moon']);

    $this->from(route('meditation-categories.index'))
        ->put(route('meditation-categories.update', $category), [
            'name' => 'Deep Sleep',
            'icon' => 'cloud-moon',
            'description' => 'Longer body scans.',
        ])->assertRedirect(route('meditation-categories.index'));

    expect($category->fresh())
        ->name->toBe('Deep Sleep')
        ->icon->toBe('cloud-moon')
        ->description->toBe('Longer body scans.');
});

test('a category keeps its own name when updated', function () {
    $category = MeditationCategory::factory()->create(['name' => 'Sleep']);

    $this->put(route('meditation-categories.update', $category), [
        'name' => 'Sleep',
        'icon' => 'moon',
    ])->assertValid();
});

test('a category cannot take another category name', function () {
    MeditationCategory::factory()->create(['name' => 'Focus']);
    $category = MeditationCategory::factory()->create(['name' => 'Sleep']);

    $this->put(route('meditation-categories.update', $category), [
        'name' => 'Focus',
        'icon' => 'moon',
    ])->assertInvalid('name');
});

test('the index carries everything the edit dialog needs', function () {
    $category = MeditationCategory::factory()->create();

    $this->get(route('meditation-categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('iconOptions')
            ->has('categories.data.0', fn (AssertableInertia $row) => $row
                ->where('id', $category->id)
                ->where('name', $category->name)
                ->where('icon', $category->icon)
                ->where('description', $category->description)
                ->etc()
            )
        );
});

test('the index reports how many sessions are filed', function () {
    $category = MeditationCategory::factory()->create();
    Meditation::factory()->count(2)->inCategory($category)->create();

    $this->get(route('meditation-categories.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('meditationCount', 2));
});

test('a category can be deleted', function () {
    $category = MeditationCategory::factory()->create();

    $this->from(route('meditation-categories.index'))
        ->delete(route('meditation-categories.destroy', $category))
        ->assertRedirect(route('meditation-categories.index'));

    expect(MeditationCategory::count())->toBe(0);
});

test('the seeder is idempotent', function () {
    $this->seed(MeditationCategorySeeder::class);
    $this->seed(MeditationCategorySeeder::class);

    expect(MeditationCategory::count())->toBe(8);
});
