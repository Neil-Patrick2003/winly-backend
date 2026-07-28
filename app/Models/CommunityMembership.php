<?php

namespace App\Models;

use Database\Factories\CommunityMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $community_id
 * @property Carbon $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Community $community
 */
#[Fillable(['user_id', 'community_id', 'joined_at'])]
class CommunityMembership extends Model
{
    /** @use HasFactory<CommunityMembershipFactory> */
    use HasFactory, HasUuids;

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
     * The community joined.
     *
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
