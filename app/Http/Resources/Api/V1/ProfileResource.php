<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the profile page shows about one person.
 *
 * The same payload serves your own profile and somebody else's, which is why
 * the private half — email, verification, admin rights, when you were last
 * seen — is gated behind `is_self` rather than left in for every reader.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSelf = $request->user()?->is($this->resource) ?? false;

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'cover_gradient' => $this->cover_gradient,

            'wins_count' => $this->wins_count,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,

            /*
             * The streak still standing, not the column.
             *
             * `streak_days` is only rewritten when a win lands, so someone who
             * stopped a week ago still has their old number on the row. The
             * profile has to show what is true today, not what was true the
             * last time they showed up.
             */
            'streak_days' => $this->currentStreak(),
            'longest_streak' => $this->longest_streak,
            'last_win_on' => $this->last_win_on?->toDateString(),

            'is_private' => $this->is_private,

            /*
             * Whether this profile belongs to the reader.
             *
             * Sent rather than left to the client to work out by comparing ids,
             * because it is also the flag saying which of the fields below are
             * present at all.
             */
            'is_self' => $isSelf,

            /*
             * Whether the reader follows this person.
             *
             * Present only where the caller loaded `followers` narrowed to the
             * reader. Absent is not the same as false, which is why this is
             * `whenLoaded`: a client that read false out of a payload that
             * never asked would offer "Follow" against someone it already
             * follows.
             */
            'is_following' => $this->whenLoaded(
                'followers',
                fn (): bool => $this->followers->isNotEmpty(),
            ),

            /*
             * Whether this person follows the reader back, for the "Follows
             * you" badge. Narrowed and conditional for the same reasons.
             */
            'follows_you' => $this->whenLoaded(
                'following',
                fn (): bool => $this->following->isNotEmpty(),
            ),

            'has_active_story' => $this->whenHas(
                'has_active_story',
                fn (mixed $value): bool => (bool) $value,
            ),

            /*
             * The half of a profile only its owner has any business reading.
             * Absent altogether rather than nulled out, so a client cannot
             * mistake "not yours to see" for "not set".
             */
            'email' => $this->when($isSelf, fn (): string => $this->email),
            'email_verified_at' => $this->when(
                $isSelf,
                fn (): ?string => $this->email_verified_at?->toIso8601String(),
            ),
            'is_admin' => $this->when($isSelf, fn (): bool => $this->is_admin),
            'last_active_at' => $this->when(
                $isSelf,
                fn (): ?string => $this->last_active_at?->toIso8601String(),
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
