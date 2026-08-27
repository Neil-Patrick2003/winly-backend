<?php

namespace App\Models;

use App\Policies\CirclePolicy;
use Database\Factories\CircleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A circle: a named group people join.
 *
 * @property string $id
 * @property string|null $owner_id
 * @property string|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property string $icon_initial
 * @property string $color_hex
 * @property string|null $tag
 * @property bool $is_private
 * @property int $members_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 */
#[Fillable(['owner_id', 'parent_id', 'name', 'description', 'icon_initial', 'color_hex', 'tag', 'is_private', 'members_count'])]
#[UsePolicy(CirclePolicy::class)]
class Circle extends Model
{
    /** @use HasFactory<CircleFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'members_count' => 0,
        'is_private' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'is_private' => 'boolean',
        ];
    }

    /**
     * Whoever made it.
     *
     * Nullable: the circles that existed before ownership did have nobody, and
     * a circle whose owner deleted their account keeps going without one.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The circle this one sits inside, if any.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The circles sitting inside this one.
     *
     * Only ever one level: a circle inside another cannot hold more, so this is
     * a list and never a tree to walk.
     *
     * @return HasMany<Circle, $this>
     */
    public function subCircles(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Whether this circle sits inside another.
     */
    public function isSubCircle(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * The users who have joined this circle.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_memberships')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * The membership rows tying users to this circle.
     *
     * @return HasMany<CircleMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMembership::class);
    }

    /**
     * The memberships that run this circle.
     *
     * The one who made it is among them: their membership carries the rank
     * too, so "who runs this" is one question with one answer rather than a
     * column and a table that have to agree.
     *
     * @return HasMany<CircleMembership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->memberships()->where('role', CircleMembership::ROLE_OWNER);
    }

    /**
     * The invitations sent for this circle, answered or not.
     *
     * @return HasMany<CircleInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CircleInvitation::class);
    }

    /**
     * The people barred from this circle.
     *
     * @return HasMany<CircleBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(CircleBlock::class);
    }

    /**
     * The posts shared into this circle.
     *
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }

    /**
     * Match circles whose name, tag or description contains the given term.
     *
     * The tag is searched alongside the prose because it is the word people
     * reach for first — someone looking for "fitness" means the tag, and a
     * search that only read the name would tell them no such circle exists.
     *
     * @param  Builder<Circle>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('tag', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        });
    }

    /**
     * Narrow to the circles this person runs.
     *
     * Both routes to it: the `owner_id` column, which names whoever made the
     * circle, and the owner rank on a membership, which is how somebody else is
     * given the same run of the place. The column is kept in the test rather
     * than leaned on entirely — an owner whose membership row went missing
     * still owns their circle, and a rule that forgot that would lock them out
     * of it.
     *
     * @param  Builder<Circle>  $query
     */
    #[Scope]
    protected function runBy(Builder $query, User $owner): void
    {
        $query->where(function (Builder $runs) use ($owner): void {
            $runs
                ->where('circles.owner_id', $owner->getKey())
                ->orWhereHas('ownerships', fn (Builder $rank) => $rank
                    ->where('user_id', $owner->getKey()));
        });
    }

    /**
     * Limit circles to a single tag.
     *
     * @param  Builder<Circle>  $query
     */
    #[Scope]
    protected function taggedWith(Builder $query, ?string $tag): void
    {
        $query->when(filled($tag), fn (Builder $query) => $query->where('tag', $tag));
    }
}
