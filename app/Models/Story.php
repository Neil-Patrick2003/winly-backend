<?php

namespace App\Models;

use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $image_url
 * @property string|null $caption
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'image_url', 'caption', 'expires_at'])]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use HasFactory, HasUuids;

    /**
     * How long a story stays visible after posting, in hours.
     */
    public const LIFETIME_HOURS = 24;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The poster.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who has seen this story.
     *
     * @return HasMany<StoryView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * The reactions left on this story.
     *
     * @return HasMany<StoryReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(StoryReaction::class);
    }

    /**
     * Limit stories to those that have not expired yet.
     *
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * Limit stories to those that have already expired.
     *
     * @param  Builder<Story>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }
}
