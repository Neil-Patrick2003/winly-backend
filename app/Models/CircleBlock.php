<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Somebody barred from a circle.
 *
 * Separate from removing them: removing takes back this membership, blocking
 * stops the next one. A blocked person cannot rejoin a public circle, cannot be
 * invited back, and does not appear in the list of people to invite.
 *
 * @property string $id
 * @property string $circle_id
 * @property string $user_id
 * @property string|null $blocked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Circle $circle
 * @property-read User $user
 */
#[Fillable(['circle_id', 'user_id', 'blocked_by'])]
class CircleBlock extends Model
{
    use HasUuids;

    /**
     * The circle they are barred from.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * The person barred.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
