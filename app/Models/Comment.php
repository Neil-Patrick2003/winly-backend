<?php

namespace App\Models;

use App\Policies\CommentPolicy;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $post_id
 * @property string $user_id
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 * @property-read User $user
 */
#[Fillable(['post_id', 'user_id', 'text'])]
#[UsePolicy(CommentPolicy::class)]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUuids;

    /**
     * The longest comment accepted.
     */
    public const MAX_TEXT_LENGTH = 2000;

    /**
     * The post being commented on.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The commenter.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order comments the way a thread reads, oldest first.
     *
     * The id breaks ties on created_at. Cursor pagination needs a total order
     * or comments sharing a timestamp can be repeated or skipped across pages.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function oldestFirst(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }

    /**
     * Order comments newest first, for taking the tail of a thread.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
