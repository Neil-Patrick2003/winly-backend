<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A small win shared to the feed.
 *
 * The kind of win a post represents lives in one of the three `win_*` tables
 * hanging off it, each of which carries the detail specific to that kind.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $caption
 * @property string|null $image_url
 * @property int $likes_count
 * @property int $comments_count
 * @property int $shares_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'caption', 'image_url', 'likes_count', 'comments_count', 'shares_count'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'likes_count' => 0,
        'comments_count' => 0,
        'shares_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
        ];
    }

    /**
     * The author.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The likes this post has received.
     *
     * @return HasMany<PostLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * The comments left on this post.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The meditation detail, when this post is a meditation win.
     *
     * @return HasOne<WinMeditation, $this>
     */
    public function winMeditation(): HasOne
    {
        return $this->hasOne(WinMeditation::class);
    }

    /**
     * The learning detail, when this post is a learning win.
     *
     * @return HasOne<WinLearning, $this>
     */
    public function winLearning(): HasOne
    {
        return $this->hasOne(WinLearning::class);
    }

    /**
     * The movement detail, when this post is a movement win.
     *
     * @return HasOne<WinMovement, $this>
     */
    public function winMovement(): HasOne
    {
        return $this->hasOne(WinMovement::class);
    }

    /**
     * Limit posts to those written by the given users.
     *
     * @param  Builder<Post>  $query
     * @param  list<string>  $userIds
     */
    #[Scope]
    protected function authoredBy(Builder $query, array $userIds): void
    {
        $query->whereIn('user_id', $userIds);
    }

    /**
     * Order posts newest first.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }
}
