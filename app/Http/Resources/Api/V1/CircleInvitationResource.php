<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CircleInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CircleInvitation
 */
class CircleInvitationResource extends JsonResource
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
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'circle' => new CircleResource($this->whenLoaded('circle')),
            'inviter' => new UserSummaryResource($this->whenLoaded('inviter')),
        ];
    }
}
