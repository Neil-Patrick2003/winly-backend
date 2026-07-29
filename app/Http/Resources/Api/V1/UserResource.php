<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'cover_gradient' => $this->cover_gradient,
            // The streak still standing rather than the stored column, so this
            // and the profile endpoint never disagree about the same user.
            'streak_days' => $this->currentStreak(),
            'longest_streak' => $this->longest_streak,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'wins_count' => $this->wins_count,
            'is_private' => $this->is_private,
            'is_admin' => $this->is_admin,
            'has_active_story' => $this->whenHas(
                'has_active_story',
                fn (mixed $value): bool => (bool) $value,
            ),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
