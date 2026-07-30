<?php

namespace App\Models;

use App\Policies\CircleInvitationPolicy;
use Database\Factories\CircleInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An ask to join a circle.
 *
 * Membership by invitation is a two-step thing: being asked is not being in.
 * The row records the ask, and only accepting it writes a membership.
 *
 * @property string $id
 * @property string $circle_id
 * @property string $inviter_id
 * @property string $invitee_id
 * @property string $status
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Circle $circle
 * @property-read User $inviter
 * @property-read User $invitee
 */
#[Fillable(['circle_id', 'inviter_id', 'invitee_id', 'status', 'responded_at'])]
#[UsePolicy(CircleInvitationPolicy::class)]
class CircleInvitation extends Model
{
    /** @use HasFactory<CircleInvitationFactory> */
    use HasFactory, HasUuids;

    /** Waiting on an answer. */
    public const PENDING = 'pending';

    /** Taken up — a membership exists. */
    public const ACCEPTED = 'accepted';

    /** Turned down. Kept rather than deleted, so re-inviting can update it. */
    public const DECLINED = 'declined';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::PENDING, self::ACCEPTED, self::DECLINED];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    /**
     * The circle being offered.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * Whoever sent it.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * Whoever it is waiting on.
     *
     * @return BelongsTo<User, $this>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    /**
     * Limit to invitations still waiting on an answer.
     *
     * @param  Builder<CircleInvitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }
}
