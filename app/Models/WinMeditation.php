<?php

namespace App\Models;

use App\Concerns\HasWinMedia;
use Database\Factories\WinMeditationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A meditation win: how long the timer ran, and whether it was seen through.
 *
 * @property string $id
 * @property string $post_id
 * @property int $duration_minutes
 * @property bool $completed
 * @property bool $media_attached
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
#[Fillable(['post_id', 'duration_minutes', 'completed', 'media_attached', 'completed_at'])]
class WinMeditation extends Model
{
    /** @use HasFactory<WinMeditationFactory> */
    use HasFactory, HasUuids, HasWinMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'win_meditation';

    /**
     * The longest sitting the timer will accept, in minutes.
     */
    public const MAX_DURATION_MINUTES = 600;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'completed' => false,
        'media_attached' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'completed' => 'boolean',
            'media_attached' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The post this win detail belongs to.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
