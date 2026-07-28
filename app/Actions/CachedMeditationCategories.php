<?php

namespace App\Actions;

use App\Http\Resources\Api\V1\MeditationCategoryResource;
use App\Models\MeditationCategory;
use Illuminate\Support\Facades\Cache;

/**
 * The category list served to the mobile app.
 *
 * The catalogue is identical for every reader and changes only when an admin
 * edits it, so it is cached once for everyone rather than per user. Writes bust
 * the key from the models' booted() hooks, which is why the entry can be held
 * for a full day without going stale.
 *
 * What lands in the cache is the finished response payload — plain arrays, not
 * Eloquent models. Models carry their connection, table and attribute casts
 * into the cache, which bloats the entry and makes it depend on class
 * definitions surviving a deploy; some drivers hand them back as
 * __PHP_Incomplete_Class. Arrays have neither problem.
 */
class CachedMeditationCategories
{
    /**
     * The cache key holding the serialised category list.
     */
    public const CACHE_KEY = 'api.v1.meditation-categories';

    /**
     * How long the list survives without a write to bust it.
     */
    public const TTL_SECONDS = 86400;

    /**
     * Get the categories, from cache when possible.
     *
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::TTL_SECONDS,
            fn (): array => $this->payload(),
        );
    }

    /**
     * Drop the cached list. Called whenever a category or meditation changes.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Build the response payload from the database.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function payload(): array
    {
        $categories = MeditationCategory::query()
            ->select(['id', 'label', 'slug', 'icon', 'description', 'updated_at'])
            ->withCount('meditationItems')
            ->orderBy('label')
            ->get();

        return MeditationCategoryResource::collection($categories)
            ->resolve();
    }
}
