<?php

namespace App\Models;

use App\Actions\CachedMeditationCategories;
use Database\Factories\MeditationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $category_id
 * @property string $title
 * @property string|null $instructions
 * @property string|null $thumbnail
 * @property string|null $audio_url
 * @property string|null $video_url
 * @property int $duration_minutes
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $duration
 * @property-read MeditationCategory $category
 * @property-read User|null $creator
 */
#[Fillable(['category_id', 'title', 'instructions', 'thumbnail', 'audio_url', 'video_url', 'duration_minutes', 'created_by'])]
class MeditationItem extends Model
{
    /** @use HasFactory<MeditationItemFactory> */
    use HasFactory, HasUuids;

    /**
     * The columns that may be sorted from the index screen.
     *
     * @var list<string>
     */
    public const SORTABLE_COLUMNS = ['title', 'duration_minutes', 'created_at', 'updated_at'];

    /**
     * The longest session the library accepts, in minutes.
     */
    public const MAX_DURATION_MINUTES = 600;

    /**
     * The cached category list carries a meditation_items_count, so it has to
     * be dropped whenever a session is added, moved between categories, or
     * removed.
     */
    protected static function booted(): void
    {
        static::saved(fn () => CachedMeditationCategories::flush());
        static::deleted(fn () => CachedMeditationCategories::flush());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * The human-readable session length.
     *
     * The length is stored as an integer so it stays sortable and filterable;
     * this renders it in the display form the clients expect.
     *
     * @return Attribute<string, never>
     */
    protected function duration(): Attribute
    {
        return Attribute::get(fn (): string => $this->duration_minutes.' min');
    }

    /**
     * The category this meditation is filed under.
     *
     * @return BelongsTo<MeditationCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MeditationCategory::class, 'category_id');
    }

    /**
     * The admin who added this session.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Match meditations whose title or instructions contain the given term.
     *
     * @param  Builder<MeditationItem>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', $term)
                    ->orWhere('instructions', 'like', $term);
            });
        });
    }

    /**
     * Limit meditations to a single category.
     *
     * @param  Builder<MeditationItem>  $query
     */
    #[Scope]
    protected function inCategory(Builder $query, ?string $categoryId): void
    {
        $query->when(filled($categoryId), fn (Builder $query) => $query->where('category_id', $categoryId));
    }

    /**
     * Limit meditations to a duration range, in minutes.
     *
     * @param  Builder<MeditationItem>  $query
     */
    #[Scope]
    protected function durationBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query->when($min !== null, fn (Builder $query) => $query->where('duration_minutes', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('duration_minutes', '<=', $max));
    }

    /**
     * Limit meditations to those created within the given date range.
     *
     * @param  Builder<MeditationItem>  $query
     */
    #[Scope]
    protected function createdBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when(filled($from), fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when(filled($to), fn (Builder $query) => $query->whereDate('created_at', '<=', $to));
    }

    /**
     * Order the meditations by a whitelisted column.
     *
     * @param  Builder<MeditationItem>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, ?string $column, ?string $direction): void
    {
        $column = in_array($column, self::SORTABLE_COLUMNS, strict: true) ? $column : 'title';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
