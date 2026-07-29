<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Circle;
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
            'members_count' => $this->members_count,
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
             * Whether the reader made it.
             *
             * Derived rather than loaded: the owner id is on the row already,
             * and the client needs the answer on every circle it draws to know
             * which ones it may manage.
             */
            'is_owner' => $this->owner_id !== null
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
}
