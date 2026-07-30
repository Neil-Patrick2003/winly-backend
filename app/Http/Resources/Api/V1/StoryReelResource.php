<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * One person's run of active stories.
 *
 * Stories are read a person at a time rather than one at a time — you open
 * someone's ring and watch what they have posted since yesterday — so the list
 * endpoint hands them over already grouped. A flat list would make every client
 * do the same grouping, and get the ordering subtly wrong in its own way.
 *
 * @property-read User $resource
 */
class StoryReelResource extends JsonResource
{
    /**
     * @param  User  $resource  The poster.
     * @param  Collection<int, Story>  $stories  Their active stories, oldest first.
     */
    public function __construct(User $resource, protected Collection $stories)
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
            'author' => new UserSummaryResource($this->resource),
            'stories' => StoryResource::collection($this->stories),
            /*
             * Whether anything here is still unwatched.
             *
             * What decides the ring: a reel with something new is worth
             * drawing brightly, and one already watched through is not. The
             * client could work this out from the stories, but every client
             * would have to.
             */
            'has_unseen' => $this->stories->contains(
                fn (Story $story): bool => ! $story->relationLoaded('viewerView')
                    || $story->viewerView === null
            ),
        ];
    }
}
