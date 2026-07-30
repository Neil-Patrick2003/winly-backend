<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-app notification.
 *
 * This is the app's own notification shape rather than Laravel's database
 * notification channel, which is unused here.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $actor_id
 * @property string $type
 * @property string|null $post_id
 * @property string $message
 * @property bool $is_read
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read User|null $actor
 * @property-read Post|null $post
 */
#[Fillable(['user_id', 'actor_id', 'type', 'post_id', 'message', 'is_read'])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    /**
     * The notification types the app emits.
     *
     * @var list<string>
     */
    public const TYPES = ['follow', 'like', 'comment', 'mention', 'streak', 'circle'];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_read' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /**
     * The recipient.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user whose action triggered this notification, when there is one.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The post this notification points at, when there is one.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Limit notifications to those the recipient has not opened.
     *
     * @param  Builder<Notification>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->where('is_read', false);
    }
}
