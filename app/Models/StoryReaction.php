<?php

namespace App\Models;

use Database\Factories\StoryReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $story_id
 * @property string $user_id
 * @property string $reaction_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $user
 */
#[Fillable(['story_id', 'user_id', 'reaction_type'])]
class StoryReaction extends Model
{
    /** @use HasFactory<StoryReactionFactory> */
    use HasFactory, HasUuids;

    /**
     * The reactions the clients offer.
     *
     * @var list<string>
     */
    public const TYPES = ['like', 'love', 'celebrate', 'support', 'insightful'];

    /**
     * The story that was reacted to.
     *
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The user who reacted.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
