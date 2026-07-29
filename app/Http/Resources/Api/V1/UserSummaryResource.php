<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The slice of a user that is safe to show beside someone else's content.
 *
 * Feeds and post payloads must not leak a poster's email or verification
 * state, so they carry this rather than the full UserResource.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
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
            'full_name' => $this->full_name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            /*
             * Whether the reader follows this user.
             *
             * Only present where the caller loaded `followers` narrowed to the
             * reader — the feed does. Absent is not the same as false, and is
             * why this is `whenLoaded` rather than a default: a client that
             * assumed false from a payload that never looked would show
             * "Follow" against people it has no business guessing about.
             */
            'is_following' => $this->whenLoaded(
                'followers',
                fn (): bool => $this->followers->isNotEmpty()
            ),
            /*
             * Whether this user has a story still running.
             *
             * Present only where the caller selected it with the
             * `withActiveStory` scope. Absent is not the same as false, for
             * the same reason as is_following above.
             */
            'has_active_story' => $this->whenHas(
                'has_active_story',
                fn (mixed $value): bool => (bool) $value,
            ),
        ];
    }
}
