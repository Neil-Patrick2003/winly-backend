<?php

namespace App\Models;

use App\Policies\StoryPolicy;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
#[UsePolicy(StoryPolicy::class)]
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
     * The reader's own view of this story, if they have seen it.
     *
     * Unconstrained this is simply the first view on the story, so it is only
     * meaningful when eager loaded against a single user. The unique index on
     * (story_id, viewer_id) guarantees there is at most one to find.
     *
     * @return HasOne<StoryView, $this>
     */
    public function viewerView(): HasOne
    {
        return $this->hasOne(StoryView::class, 'story_id');
    }

    /**
     * The reader's own reaction to this story, if they left one.
     *
     * Unconstrained this is simply the first reaction on the story, so it is
     * only meaningful when eager loaded against a single user — the same
     * bargain as `viewerView`. The unique index on (story_id, user_id)
     * guarantees there is at most one to find.
     *
     * @return HasOne<StoryReaction, $this>
     */
    public function viewerReaction(): HasOne
    {
        return $this->hasOne(StoryReaction::class, 'story_id');
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
