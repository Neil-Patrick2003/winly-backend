<?php

namespace App\Models;

use Database\Factories\CircleMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $circle_id
 * @property string $role
 * @property Carbon $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Circle $circle
 */
#[Fillable(['user_id', 'circle_id', 'role', 'joined_at'])]
class CircleMembership extends Model
{
    /** @use HasFactory<CircleMembershipFactory> */
    use HasFactory, HasUuids;

    /**
     * Runs the circle: everything the person who made it can do.
     *
     * There is no lesser rank between this and an ordinary member. Somebody
     * trusted with the running of a group is trusted with all of it — a rank
     * that could invite but not remove would be a rank whose holder still has
     * to go and find the owner.
     */
    public const ROLE_OWNER = 'owner';

    /**
     * In the circle, and that is all.
     */
    public const ROLE_MEMBER = 'member';

    /**
     * @var list<string>
     */
    public const ROLES = [self::ROLE_OWNER, self::ROLE_MEMBER];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_MEMBER,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    /**
     * The member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The circle joined.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }
}
