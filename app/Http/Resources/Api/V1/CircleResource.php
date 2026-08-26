<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Circle
 */
class CircleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon_initial' => $this->icon_initial,
            'color_hex' => $this->color_hex,
            'tag' => $this->tag,
            'is_private' => $this->is_private,
            /*
             * The circle this one sits inside, or null when it stands alone.
             *
             * The id alone rather than the circle itself: a client drawing a
             * sub-circle already has the parent on screen, and nesting one
             * resource inside another would send the same rows twice.
             */
            'parent_id' => $this->parent_id,
            'is_sub_circle' => $this->parent_id !== null,
            /*
             * The parent by name, where the caller loaded it.
             *
             * Just the two fields a screen needs to say "Beginners (Morning
             * Sitters)". The whole circle would be the same rows again, and a
             * client that wants it can ask for it by id.
             */
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),
            'members_count' => $this->members_count,
            /*
             * How many of the reader's own wins are not on this wall yet.
             *
             * Absent where the endpoint did not work it out — a list of circles
             * would be a query each, and the answer is only needed on the one
             * screen that offers to do something about it.
             */
            'syncable_posts_count' => $this->whenHas(
                'syncable_posts_count',
                fn (mixed $value): int => (int) $value,
            ),
            /*
             * How much has been shared into it.
             *
             * Counted per query rather than kept in a column beside
             * `members_count`: a counter has to be moved by hand everywhere a
             * post appears or goes, and one that drifts is worse than one that
             * costs a subquery. Absent where the caller did not ask.
             */
            'posts_count' => $this->whenHas('posts_count', fn (mixed $value): int => (int) $value),
            /*
             * Whether the reader runs it.
             *
             * What the client draws its manage affordances off, so it answers
             * for a second owner as well as for the founder — the two hold the
             * same rank, and a screen that offered the controls to only one of
             * them would be hiding abilities the server grants.
             *
             * The owner id is on the row already; the rank behind the second
             * route is read once per request rather than per circle.
             */
            'is_owner' => $this->runsFor($request->user()),
            /*
             * Whether the reader made it, for a screen that wants to tell the
             * founder apart from whoever helps run it.
             */
            'is_founder' => $this->owner_id !== null
                && $this->owner_id === $request->user()?->getKey(),
            /*
             * Whether the reader is in it. Present only where the caller
             * selected it — absent is not the same as false, and a client that
             * assumed otherwise would offer "Join" for circles it is already
             * in.
             */
            'is_member' => $this->whenHas('is_member', fn (mixed $value): bool => (bool) $value),
            'owner' => new UserSummaryResource($this->whenLoaded('owner')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Whether this person runs the circle.
     *
     * The founder, off the column already on the row, or somebody since given
     * the same run of it — a list the user model reads once and keeps, so a
     * page of thirty circles asks once rather than thirty times.
     */
    protected function runsFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return ($this->owner_id !== null && $this->owner_id === $user->getKey())
            || $user->holdsOwnerRankIn($this->id);
    }
}
