<?php

namespace App\Models;

use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $icon_initial
 * @property string $color_hex
 * @property string|null $tag
 * @property int $members_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'icon_initial', 'color_hex', 'tag', 'members_count'])]
class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory, HasUuids;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'members_count' => 0,
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
        ];
    }

    /**
     * The users who have joined this community.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_memberships')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * The membership rows tying users to this community.
     *
     * @return HasMany<CommunityMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMembership::class);
    }

    /**
     * Match communities whose name or description contains the given term.
     *
     * @param  Builder<Community>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        });
    }

    /**
     * Limit communities to a single tag.
     *
     * @param  Builder<Community>  $query
     */
    #[Scope]
    protected function taggedWith(Builder $query, ?string $tag): void
    {
        $query->when(filled($tag), fn (Builder $query) => $query->where('tag', $tag));
    }
}
