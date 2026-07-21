<?php

namespace App\Models;

use Database\Factories\MeditationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $title
 * @property string|null $description
 * @property string|null $thumbnail
 * @property string|null $audio_url
 * @property string|null $video_url
 * @property int $duration_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MeditationCategory $category
 */
#[Fillable(['category_id', 'title', 'description', 'thumbnail', 'audio_url', 'video_url', 'duration_minutes'])]
class Meditation extends Model
{
    /** @use HasFactory<MeditationFactory> */
    use HasFactory;

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
     * The category this meditation is filed under.
     *
     * @return BelongsTo<MeditationCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MeditationCategory::class, 'category_id');
    }

    /**
     * Match meditations whose title or description contains the given term.
     *
     * @param  Builder<Meditation>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        });
    }

    /**
     * Limit meditations to a single category.
     *
     * @param  Builder<Meditation>  $query
     */
    #[Scope]
    protected function inCategory(Builder $query, ?int $categoryId): void
    {
        $query->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId));
    }

    /**
     * Limit meditations to a duration range, in minutes.
     *
     * @param  Builder<Meditation>  $query
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
     * @param  Builder<Meditation>  $query
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
     * @param  Builder<Meditation>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, ?string $column, ?string $direction): void
    {
        $column = in_array($column, self::SORTABLE_COLUMNS, strict: true) ? $column : 'title';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
