<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somebody worth following, as the Discover list needs them.
 *
 * The person, plus the two numbers that answer "why them": how often they post,
 * and whether they are still at it.
 *
 * @mixin User
 */
class SuggestedPersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new UserSummaryResource($this->resource))->toArray($request),
            /*
             * How many times they have posted. Read through `getAttribute`
             * because it is selected by the query rather than being a column
             * on the table.
             */
            'posts_count' => (int) $this->resource->getAttribute('posts_count'),
            /*
             * The run still standing rather than the stored column, so this
             * agrees with the badge on the home screen and the profile.
             */
            'streak_days' => $this->currentStreak(),
        ];
    }
}
