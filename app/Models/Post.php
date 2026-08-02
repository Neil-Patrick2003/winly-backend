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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property int $likes_count
 * @property int $comments_count
 * @property int $shares_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'caption', 'likes_count', 'comments_count', 'shares_count'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUuids;

    /**
     * The kinds of win a post may record, one table each.
     *
     * @var list<string>
     */
    public const WIN_TYPES = ['meditation', 'learning', 'movement'];

    /**
     * Take the wins' media with the post.
     *
     * The win rows go by database cascade, but their media cannot: it hangs off
     * a win by a polymorphic pair, which carries no foreign key, and a cascade
     * fires no events for anything to listen for. Without this a deleted post
     * would leave rows pointing at wins that no longer exist.
     *
     * The API's own delete has already taken the media away by the time this
     * runs — it does so before committing, so that the files are only unlinked
     * once the deletion is certain. This is for every other way a post can go:
     * a factory, the console, a cleanup script.
     */
    protected static function booted(): void
    {
        static::deleting(function (Post $post): void {
            foreach ([$post->winMeditation, $post->winLearning, $post->winMovement] as $win) {
                $win?->deleteAllMedia();
            }
        });
    }

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
     * The circles it was shared into. Empty means shared openly.
     *
     * @return BelongsToMany<Circle, $this>
     */
    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class)->withTimestamps();
    }

    /**
     * Whether this reader is allowed to see the post at all.
     *
     * Always true: a circle is where a post is *placed*, not who it is kept
     * from. Sharing into circles puts a win on their walls and takes nothing
     * away from anybody else — there is no such thing as a post only a circle
     * can read.
     *
     * Kept as a method rather than deleted because callers still ask, and
     * because that stops being true the moment private circles land: this is
     * the one place that would need to know.
     */
    public function isVisibleTo(User $reader): bool
    {
        return true;
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
     * The reaction the user reading the feed left on this post, if any.
     *
     * Unconstrained this is simply the first like on the post, so it is only
     * meaningful when eager loaded against a single user. The unique index on
     * (post_id, user_id) guarantees there is at most one to find.
     *
     * @return HasOne<PostLike, $this>
     */
    public function viewerLike(): HasOne
    {
        return $this->hasOne(PostLike::class);
    }

    /**
     * Everyone who has kept this post to come back to.
     *
     * Nobody is ever shown this, and nothing counts it — a save is private to
     * whoever made it. It exists so the rows go when the post does.
     *
     * @return HasMany<SavedPost, $this>
     */
    public function saves(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    /**
     * Whether the person reading has kept this post, as their own row.
     *
     * Unconstrained this is simply the first save on the post, so — like
     * `viewerLike` — it is only meaningful eager loaded against one user. The
     * unique index on (user_id, post_id) guarantees there is at most one.
     *
     * @return HasOne<SavedPost, $this>
     */
    public function viewerSave(): HasOne
    {
        return $this->hasOne(SavedPost::class);
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
     * Limit posts to those written by people the reader follows.
     *
     * A subquery rather than a plucked list of ids: somebody following
     * thousands of people would otherwise pull every one of those ids into
     * memory to ask a question the database can answer on its own.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function followedBy(Builder $query, User $reader): void
    {
        $query->whereIn('user_id', $reader->following()->getQuery()->select('users.id'));
    }

    /**
     * Limit posts to those shared into any circle the reader belongs to.
     *
     * `whereHas` rather than a join, because a post shared into three circles a
     * reader is in must still come back once — a join would hand it over three
     * times and the feed would repeat it.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function inCirclesOf(Builder $query, User $reader): void
    {
        $query->whereHas('circles', fn (Builder $circles) => $circles->whereIn(
            'circles.id',
            $reader->circles()->getQuery()->select('circles.id')
        ));
    }

    /**
     * Order posts newest first.
     *
     * The id breaks ties on created_at. Cursor pagination needs a total order
     * or posts sharing a timestamp can be repeated or skipped across pages.
     *
     * Both columns are qualified because this scope is applied to the circle
     * relation as well as to the table on its own, and `circle_post` carries a
     * `created_at` of its own. Left bare, MySQL rejects the query outright as
     * ambiguous; SQLite quietly picks one, which is worse, since the tests
     * would go on passing while production fell over.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query
            ->orderByDesc($query->qualifyColumn('created_at'))
            ->orderByDesc($query->qualifyColumn('id'));
    }
}
