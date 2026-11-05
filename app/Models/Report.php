<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Somebody flagging a post, comment, story or person for staff to look at.
 *
 * Filing one hides nothing by itself; it puts a row on the queue and emails
 * whoever handles moderation. Blocking is what hides things from the reporter.
 *
 * @property string $id
 * @property string $reporter_id
 * @property string $reportable_type
 * @property string $reportable_id
 * @property string $reason
 * @property string|null $note
 * @property string $status
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'note', 'status', 'reviewed_at', 'reviewed_by'])]
class Report extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * Why something is being reported, and how each reads to a person.
     *
     * The keys are what the app sends. Kept short: a long list makes people
     * pick "other" rather than read it.
     */
    public const REASONS = [
        'spam' => 'Spam or scam',
        'harassment' => 'Harassment or bullying',
        'hate' => 'Hate speech',
        'sexual' => 'Nudity or sexual content',
        'violence' => 'Violence or threats',
        'self_harm' => 'Self-harm or suicide',
        'other' => 'Something else',
    ];

    /**
     * What can be reported, by the name the app uses for it.
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        'story' => Story::class,
        'user' => User::class,
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The app's short name for what was reported: post, comment, story, user.
     */
    public function typeName(): string
    {
        return (string) array_search($this->reportable_type, self::TYPES, true);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
