<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One device that can be reached with a push notification.
 *
 * @property string $id
 * @property string $user_id
 * @property string $token
 * @property string|null $platform
 * @property Carbon|null $failed_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'token', 'platform', 'failed_at'])]
class PushToken extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['failed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Devices still worth sending to.
     *
     * @param  Builder<PushToken>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereNull('failed_at');
    }
}
