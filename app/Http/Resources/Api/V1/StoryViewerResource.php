<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StoryView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somebody who watched a story, and when.
 *
 * Flattened rather than nested under a `user` key: every list of people in this
 * API hands back the same summary shape, and a viewer is a person with a
 * timestamp attached — not a wrapper around one. Clients can reuse whatever
 * they already draw a person with.
 *
 * @mixin StoryView
 */
class StoryViewerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new UserSummaryResource($this->viewer))->toArray($request),
            /*
             * When they watched it, not when the row was written.
             *
             * The two are the same today, but `viewed_at` is the fact being
             * recorded and `created_at` is bookkeeping about the record.
             */
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            /*
             * What they left on it, or null for the many who just watched.
             *
             * Selected alongside the view rather than loaded as a relation —
             * see the subquery in `StoryController::viewers`.
             */
            'reaction_type' => $this->reaction_type,
        ];
    }
}
