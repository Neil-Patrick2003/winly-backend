<?php

namespace App\Models;

use Database\Factories\StoryViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $story_id
 * @property string $viewer_id
 * @property Carbon $viewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $viewer
 */
#[Fillable(['story_id', 'viewer_id', 'viewed_at'])]
class StoryView extends Model
{
    /** @use HasFactory<StoryViewFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * The story that was seen.
     *
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * The user who saw it.
     *
     * @return BelongsTo<User, $this>
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}
