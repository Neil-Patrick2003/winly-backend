<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Story
 */
class StoryResource extends JsonResource
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
            'image_url' => $this->image_url,
            'caption' => $this->caption,
            'expires_at' => $this->expires_at?->toIso8601String(),
            // Saves the client comparing timestamps against a clock that may
            // not agree with the server's.
            'is_active' => $this->expires_at?->isFuture() ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => new UserSummaryResource($this->whenLoaded('user')),
        ];
    }
}
