<?php

namespace App\Models;

use Database\Factories\FollowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $follower_id
 * @property string $followee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $follower
 * @property-read User $followee
 */
#[Fillable(['follower_id', 'followee_id'])]
class Follow extends Model
{
    /** @use HasFactory<FollowFactory> */
    use HasFactory, HasUuids;

    /**
     * The user doing the following.
     *
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * The user being followed.
     *
     * @return BelongsTo<User, $this>
     */
    public function followee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followee_id');
    }
}
