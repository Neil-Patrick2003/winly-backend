<?php

namespace App\Models;

use Database\Factories\HabitLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $habit_id
 * @property string $user_id
 * @property Carbon $date
 * @property float $value_logged
 * @property bool $completed
 * @property Carbon $logged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Habit $habit
 * @property-read User $user
 */
#[Fillable(['habit_id', 'user_id', 'date', 'value_logged', 'completed', 'logged_at'])]
class HabitLog extends Model
{
    /** @use HasFactory<HabitLogFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'value_logged' => 0,
        'completed' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value_logged' => 'float',
            'completed' => 'boolean',
            'logged_at' => 'datetime',
        ];
    }

    /**
     * The habit this entry belongs to.
     *
     * @return BelongsTo<Habit, $this>
     */
    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    /**
     * The user who logged it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit entries to those logged within the given date range.
     *
     * @param  Builder<HabitLog>  $query
     */
    #[Scope]
    protected function loggedBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when(filled($from), fn (Builder $query) => $query->whereDate('date', '>=', $from))
            ->when(filled($to), fn (Builder $query) => $query->whereDate('date', '<=', $to));
    }
}
