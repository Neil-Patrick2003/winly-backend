<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where the caller stands with the user they just followed or let go of.
 *
 * @mixin User
 */
class FollowStateResource extends JsonResource
{
    /**
     * @param  User  $resource  The user who was followed or unfollowed.
     * @param  bool  $isFollowing  Whether the caller follows them now.
     */
    public function __construct(User $resource, protected bool $isFollowing)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserSummaryResource($this->resource),
            'is_following' => $this->isFollowing,
            'followers_count' => $this->followers_count,
        ];
    }
}
