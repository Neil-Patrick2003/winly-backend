<?php

namespace App\Models;

use Database\Factories\SavedPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Somebody keeping a post to come back to.
 *
 * Private, and one-sided: nothing about it is shown to the person who wrote the
 * post, and there is no count of it anywhere — unlike a like, which is a thing
 * said out loud.
 *
 * @property string $id
 * @property string $user_id
 * @property string $post_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 * @property-read User $user
 */
#[Fillable(['user_id', 'post_id'])]
class SavedPost extends Model
{
    /** @use HasFactory<SavedPostFactory> */
    use HasFactory, HasUuids;

    /**
     * The post that was kept.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The person who kept it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
