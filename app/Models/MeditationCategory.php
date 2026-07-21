<?php

namespace App\Models;

use Database\Factories\MeditationCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $icon
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'icon', 'description'])]
class MeditationCategory extends Model
{
    /** @use HasFactory<MeditationCategoryFactory> */
    use HasFactory;

    /**
     * The columns that may be sorted from the index screen.
     *
     * @var list<string>
     */
    public const SORTABLE_COLUMNS = ['name', 'created_at', 'updated_at'];

    /**
     * The icon names offered by the frontend icon picker.
     *
     * @var list<string>
     */
    public const ICONS = [
        'brain',
        'cloud-moon',
        'flower-2',
        'hand-heart',
        'heart-pulse',
        'leaf',
        'moon',
        'mountain-snow',
        'music',
        'sparkles',
        'sun',
        'sunrise',
        'waves',
        'wind',
    ];

    /**
     * The meditations filed under this category.
     *
     * @return HasMany<Meditation, $this>
     */
    public function meditations(): HasMany
    {
        return $this->hasMany(Meditation::class, 'category_id');
    }

    /**
     * Match categories whose name or description contains the given term.
     *
     * @param  Builder<MeditationCategory>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        });
    }

    /**
     * Limit categories to those created within the given date range.
     *
     * @param  Builder<MeditationCategory>  $query
     */
    #[Scope]
    protected function createdBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when(filled($from), fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when(filled($to), fn (Builder $query) => $query->whereDate('created_at', '<=', $to));
    }

    /**
     * Order the categories by a whitelisted column.
     *
     * @param  Builder<MeditationCategory>  $query
     */
    #[Scope]
    protected function sorted(Builder $query, ?string $column, ?string $direction): void
    {
        $column = in_array($column, self::SORTABLE_COLUMNS, strict: true) ? $column : 'name';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
