<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCircle
{
    /**
     * The colours a new circle's badge is drawn from.
     *
     * Picked from the name rather than asked for: naming a circle is the whole
     * of what someone came to do, and a colour picker in front of that is a
     * decision nobody wanted. The same name always lands on the same colour, so
     * it does not appear to change when the list reloads.
     *
     * @var list<string>
     */
    public const PALETTE = ['#946FF0', '#609BF1', '#60BC88', '#E6AC49', '#E0759C', '#4FB6C4'];

    /**
     * Start a circle.
     *
     * Whoever makes it is its first member: a circle you made but are not in
     * would need explaining, and every count on the screen would have to
     * special-case it.
     *
     * @param  array{name: string, description?: string|null, tag?: string|null, parent_id?: string|null}  $attributes
     */
    public function execute(User $owner, array $attributes): Circle
    {
        $name = trim($attributes['name']);

        return DB::transaction(function () use ($owner, $name, $attributes): Circle {
            $circle = Circle::create([
                'owner_id' => $owner->getKey(),
                // Null for a circle in its own right; set when it sits
                // inside another. The caller has already established that the
                // parent is theirs and is not itself a sub-circle.
                'parent_id' => $attributes['parent_id'] ?? null,
                'name' => $name,
                'description' => $attributes['description'] ?? null,
                'tag' => $attributes['tag'] ?? null,
                'icon_initial' => Str::upper(Str::substr($name, 0, 1)),
                'color_hex' => $this->colourFor($name),
                'is_private' => false,
                'members_count' => 1,
            ]);

            CircleMembership::create([
                'user_id' => $owner->getKey(),
                'circle_id' => $circle->getKey(),
                'joined_at' => now(),
            ]);

            return $circle;
        });
    }

    /**
     * The colour this name always lands on.
     */
    public function colourFor(string $name): string
    {
        return self::PALETTE[abs(crc32(Str::lower($name))) % count(self::PALETTE)];
    }
}
