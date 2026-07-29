<?php

namespace App\Models;

use Database\Factories\WinMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A photo or clip hanging off one win.
 *
 * @property string $id
 * @property string $post_id
 * @property string $win_type
 * @property string $win_id
 * @property string $url
 * @property string $kind
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
#[Fillable(['post_id', 'url', 'kind', 'position'])]
class WinMedia extends Model
{
    /** @use HasFactory<WinMediaFactory> */
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'win_media';

    /**
     * The kinds of file a win may carry.
     *
     * @var list<string>
     */
    public const KINDS = ['image', 'video'];

    /**
     * The photo formats accepted, by mime type.
     *
     * @var list<string>
     */
    public const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    /**
     * The clip formats accepted, by mime type.
     *
     * @var list<string>
     */
    public const VIDEO_MIMES = [
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * The largest photo accepted, in kilobytes.
     */
    public const MAX_IMAGE_KB = 10240;

    /**
     * The largest clip accepted, in kilobytes.
     */
    public const MAX_VIDEO_KB = 102400;

    /**
     * The most files one win may carry.
     */
    public const MAX_PER_WIN = 10;

    /**
     * The kind a given mime type maps to, or null when it is not accepted.
     */
    public static function kindForMime(?string $mime): ?string
    {
        return match (true) {
            in_array($mime, self::IMAGE_MIMES, strict: true) => 'image',
            in_array($mime, self::VIDEO_MIMES, strict: true) => 'video',
            default => null,
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * The win this file belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function win(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The post the owning win sits on.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
