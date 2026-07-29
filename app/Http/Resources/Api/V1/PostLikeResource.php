<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How a post stands after the caller liked it, or took the like back.
 *
 * The count travels with the like so a client never has to guess at the new
 * total, or refetch the post to find it.
 *
 * @mixin Post
 */
class PostLikeResource extends JsonResource
{
    /**
     * @param  Post  $resource  The post that was liked.
     * @param  bool  $hasLiked  Whether the caller likes it now.
     */
    public function __construct(Post $resource, protected bool $hasLiked)
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
            'post_id' => $this->id,
            'viewer_has_liked' => $this->hasLiked,
            'likes_count' => $this->likes_count,
        ];
    }
}
