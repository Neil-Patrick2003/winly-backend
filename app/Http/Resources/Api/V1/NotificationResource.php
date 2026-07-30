<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'message' => $this->message,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            /*
             * Who did it, and what they did it to — the two things the client
             * needs to know where a tap should land. A follow has no post and
             * opens the actor's profile; the rest open the post.
             */
            'actor' => new UserSummaryResource($this->whenLoaded('actor')),
            'post_id' => $this->post_id,
        ];
    }
}
