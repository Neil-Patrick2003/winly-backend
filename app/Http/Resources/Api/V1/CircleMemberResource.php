<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CircleMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somebody in a circle, and when they joined.
 *
 * Flattened rather than nested under a `user` key, for the same reason
 * `StoryViewerResource` is: every list of people in this API hands back the
 * same summary shape, so clients reuse whatever they already draw a person
 * with.
 *
 * @mixin CircleMembership
 */
class CircleMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new UserSummaryResource($this->user))->toArray($request),
            'joined_at' => $this->joined_at->toIso8601String(),
            /*
             * Whether this member made the circle, so the list can mark them.
             * Loaded with the membership's circle where the caller asked for
             * it; absent otherwise.
             */
            'is_owner' => $this->when(
                $this->relationLoaded('circle'),
                fn (): bool => $this->circle->owner_id === $this->user_id,
            ),
        ];
    }
}
