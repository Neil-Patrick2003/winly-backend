<?php

namespace App\Models;

use App\Concerns\HasWinMedia;
use Database\Factories\WinLearningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $post_id
 * @property string $learned_text
 * @property string|null $reference_source
 * @property bool $media_attached
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
#[Fillable(['post_id', 'learned_text', 'reference_source', 'media_attached', 'completed_at'])]
class WinLearning extends Model
{
    /** @use HasFactory<WinLearningFactory> */
    use HasFactory, HasUuids, HasWinMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'win_learning';

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
