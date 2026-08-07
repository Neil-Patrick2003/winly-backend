<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PostLike;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Somebody who liked a post, and when.
 *
 * Flattened rather than nested under a `user` key, for the same reason
 * `StoryViewerResource` is: every list of people in this API hands back the
 * same summary shape, and a liker is a person with a timestamp attached — not
 * a wrapper around one. Clients reuse whatever they already draw a person with.
 *
 * @mixin PostLike
 */
class PostLikerResource extends JsonResource
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
            /*
             * When they liked it. The row is only ever written once — a like
             * taken back is deleted rather than flagged — so `created_at` is
             * the fact rather than bookkeeping about it.
             */
            'liked_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
