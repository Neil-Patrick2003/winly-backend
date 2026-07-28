<?php

use App\Actions\CachedMeditationCategories;
use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::flush();
});

function categoryQueryCount(): int
{
    return collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'meditation_categories'))
        ->count();
}

test('guests cannot list categories', function () {
    $this->getJson(route('api.v1.meditation-categories.index'))->assertUnauthorized();
});

test('it lists categories alphabetically with their session counts', function () {
    Sanctum::actingAs(User::factory()->create());

    $sleep = MeditationCategory::factory()->create(['label' => 'Sleep', 'icon' => 'moon']);
    MeditationCategory::factory()->create(['label' => 'Focus', 'icon' => 'brain']);
    MeditationItem::factory()->count(2)->inCategory($sleep)->create();

    $this->getJson(route('api.v1.meditation-categories.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.label', 'Focus')
        ->assertJsonPath('data.0.meditation_items_count', 0)
        ->assertJsonPath('data.1.label', 'Sleep')
        ->assertJsonPath('data.1.icon', 'moon')
        ->assertJsonPath('data.1.meditation_items_count', 2)
        ->assertJsonStructure(['data' => [['id', 'label', 'slug', 'icon', 'description', 'meditation_items_count', 'updated_at']]]);
});

test('a second request is served from cache without touching the table', function () {
    Sanctum::actingAs(User::factory()->create());
    MeditationCategory::factory()->count(3)->create();

    $this->getJson(route('api.v1.meditation-categories.index'))->assertOk();

    DB::enableQueryLog();

    $this->getJson(route('api.v1.meditation-categories.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect(categoryQueryCount())->toBe(0);
});

test('creating a category busts the cache', function () {
    Sanctum::actingAs(User::factory()->create());
    MeditationCategory::factory()->create(['label' => 'Sleep']);

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(1, 'data');

    MeditationCategory::factory()->create(['label' => 'Focus']);

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(2, 'data');
});

test('relabelling a category busts the cache', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = MeditationCategory::factory()->create(['label' => 'Sleep']);

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonPath('data.0.label', 'Sleep');

    $category->update(['label' => 'Deep Sleep']);

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonPath('data.0.label', 'Deep Sleep');
});

test('deleting a category busts the cache', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = MeditationCategory::factory()->create();

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(1, 'data');

    $category->delete();

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(0, 'data');
});

test('adding a meditation busts the cache so counts stay accurate', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = MeditationCategory::factory()->create();

    $this->getJson(route('api.v1.meditation-categories.index'))
        ->assertJsonPath('data.0.meditation_items_count', 0);

    MeditationItem::factory()->inCategory($category)->create();

    $this->getJson(route('api.v1.meditation-categories.index'))
        ->assertJsonPath('data.0.meditation_items_count', 1);
});

test('an unchanged list is revalidated with a 304', function () {
    Sanctum::actingAs(User::factory()->create());
    MeditationCategory::factory()->create();

    $etag = $this->getJson(route('api.v1.meditation-categories.index'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeader('If-None-Match', $etag)
        ->getJson(route('api.v1.meditation-categories.index'))
        ->assertStatus(304);
});

test('the cached payload is plain data, not eloquent models', function () {
    MeditationCategory::factory()->create();

    app(CachedMeditationCategories::class)();

    $cached = Cache::get(CachedMeditationCategories::CACHE_KEY);

    // Caching models drags their connection, table and casts into the store,
    // and some drivers hand them back as __PHP_Incomplete_Class.
    expect($cached)->toBeArray();
    expect($cached[0])->toBeArray();
    expect(collect($cached)->flatten()->filter(fn ($value) => is_object($value)))->toBeEmpty();
});

test('the admin screens bust the cache too', function () {
    Sanctum::actingAs(User::factory()->create());
    MeditationCategory::factory()->create(['label' => 'Sleep']);

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(1, 'data');

    $this->post(route('meditation-categories.store'), ['label' => 'Focus', 'icon' => 'brain']);

    expect(Cache::has(CachedMeditationCategories::CACHE_KEY))->toBeFalse();

    $this->getJson(route('api.v1.meditation-categories.index'))->assertJsonCount(2, 'data');
});
