<?php

namespace App\Models;

use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property int $daily_goal
 * @property string $unit
 * @property string $icon
 * @property string $color_hex
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'type', 'daily_goal', 'unit', 'icon', 'color_hex'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory, HasUuids;

    /**
     * The habit types the clients offer.
     *
     * @var list<string>
     */
    public const TYPES = ['water', 'steps', 'sleep', 'meditation', 'reading', 'exercise'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_goal' => 'integer',
        ];
    }

    /**
     * The owner of this habit.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The daily entries logged against this habit.
     *
     * @return HasMany<HabitLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }
}
