<?php

namespace App\Concerns;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Builds the circle shapes the front end reads.
 *
 * Shared rather than written twice: "My Circles" and the staff list draw the
 * same card from the same fields, and two copies of this would drift the moment
 * one of them gained a column.
 */
trait DescribesCircles
{
    /**
     * The pastels a circle's card can be washed in.
     *
     * Picked from the name, like the badge colour, so a circle looks the same
     * every time it is drawn.
     *
     * @var list<string>
     */
    public const WASHES = ['blue', 'lavender', 'pink', 'peach', 'mint', 'butter'];

    /**
     * How many faces are stacked on a card.
     */
    public const FACES_ON_CARD = 3;

    /**
     * A circle described for whoever is reading it.
     *
     * `can_manage` is the reader's own permission rather than a fact about the
     * circle: an owner has it over theirs, and staff over all of them.
     *
     * @return array<string, mixed>
     */
    protected function circleCard(User $reader, Circle $circle): array
    {
        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'description' => $circle->description,
            'icon_initial' => $circle->icon_initial,
            'color_hex' => $circle->color_hex,
            'tag' => $circle->tag,
            'members_count' => $circle->members_count,
            /*
             * The circle this one sits inside, by name.
             *
             * Carried on every card so a circle reads the same wherever it is
             * named — "finance (meta)". Null for one standing on its own, and
             * loaded lazily here because these cards are built one at a time
             * from a collection the caller has usually already eager loaded.
             */
            'parent' => $circle->parent === null ? null : [
                'id' => $circle->parent->id,
                'name' => $circle->parent->name,
            ],
            'can_manage' => $reader->can('manage', $circle),
        ];
    }

    /**
     * A circle as a list of cards shows it.
     *
     * @return array<string, mixed>
     */
    protected function circleListing(User $reader, Circle $circle): array
    {
        return [
            ...$this->circleCard($reader, $circle),
            'wash' => $this->washFor($circle->name),
            'posts_count' => $circle->posts_count,
            'is_active' => (int) $circle->getAttribute('recent_posts_count') > 0,
            'faces' => $circle->members
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'avatar_url' => $member->avatar_url,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The wash this name always lands on.
     */
    protected function washFor(string $name): string
    {
        return self::WASHES[abs(crc32(Str::lower($name))) % count(self::WASHES)];
    }
}
