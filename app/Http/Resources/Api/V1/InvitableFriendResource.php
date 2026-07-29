<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A friend, as the invite screen needs them: the person plus where they stand
 * with this circle.
 *
 * @mixin User
 */
class InvitableFriendResource extends JsonResource
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
            'is_member' => $this->whenHas('is_member', fn (mixed $value): bool => (bool) $value),
            /*
             * `pending`, `accepted`, `declined`, or null where this circle has
             * never asked them. Drives the button: invite, or a pending label.
             */
            'invite_status' => $this->resource->getAttribute('invite_status'),
        ];
    }
}
