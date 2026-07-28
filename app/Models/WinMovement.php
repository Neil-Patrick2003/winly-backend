<?php

namespace App\Models;

use Database\Factories\WinMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $post_id
 * @property string $movement_type
 * @property bool $media_attached
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
#[Fillable(['post_id', 'movement_type', 'media_attached', 'completed_at'])]
class WinMovement extends Model
{
    /** @use HasFactory<WinMovementFactory> */
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'win_movement';

    /**
     * The movement types the clients offer.
     *
     * @var list<string>
     */
    public const TYPES = ['walk', 'run', 'cycle', 'swim', 'gym', 'yoga', 'stretch', 'other'];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
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
