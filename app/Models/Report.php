<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Somebody flagging something for staff to look at.
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
 * @property-read User $reporter
 * @property-read Model $reportable
 */
#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'note'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    /**
     * Why something was reported.
     *
     * Kept as a fixed list rather than free text so the queue can be sorted and
     * counted, and so the person reporting picks from the same words every
     * time. `other` is what makes the list safe to keep short — anything it
     * does not cover goes there with a note.
     *
     * The app shows these in the same order, with its own wording; the values
     * are the contract between them.
     *
     * @var list<string>
     */
    public const REASONS = [
        'spam',
        'harassment',
        'hate',
        'violence',
        'nudity',
        'self_harm',
        'misinformation',
        'impersonation',
        'other',
    ];

    /**
     * The kinds of thing that can be reported.
     *
     * A whitelist keyed by the short name the app sends, so a request cannot
     * name an arbitrary model class and have a report attached to it.
     *
     * @var array<string, class-string<Model>>
     */
    public const REPORTABLE = [
        'post' => Post::class,
        'comment' => Comment::class,
        'story' => Story::class,
        'user' => User::class,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * The longest note accepted alongside a report.
     */
    public const MAX_NOTE_LENGTH = 1000;

    /**
     * The person who made the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The staff member who dealt with it, once somebody has.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The post, comment, story or person being reported.
     *
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Still waiting on somebody, oldest first — the queue as staff work it.
     *
     * @param  Builder<Report>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING)->oldest();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
