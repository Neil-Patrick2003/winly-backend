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
            /*
             * Whether the reader has already seen this one.
             *
             * False when the relation was not loaded, which is the honest
             * answer for the endpoints that never look: creating a story tells
             * you nothing about who has watched it, and its poster has not.
             */
            'viewed' => $this->relationLoaded('viewerView')
                ? $this->viewerView !== null
                : false,
            /*
             * How many people have watched this one.
             *
             * Shown to the poster and to nobody else — who watched your story
             * is yours to know, and the same number handed to the rest of the
             * audience turns a story into a scoreboard. Absent where the count
             * was never loaded, which is every endpoint but the list.
             */
            'views_count' => $this->when(
                $request->user()?->getKey() === $this->user_id,
                fn (): mixed => $this->whenHas('views_count', fn (mixed $value): int => (int) $value),
            ),
            /*
             * How many reactions it has drawn, again for the poster alone —
             * the same reasoning as the view count.
             */
            'reactions_count' => $this->when(
                $request->user()?->getKey() === $this->user_id,
                fn (): mixed => $this->whenHas('reactions_count', fn (mixed $value): int => (int) $value),
            ),
            /*
             * Which reactions came in, most common first — the poster's alone,
             * and the distinct kinds rather than one entry per person, since
             * this draws a small row of emoji beside the count.
             */
            'reaction_types' => $this->when(
                $request->user()?->getKey() === $this->user_id && $this->relationLoaded('reactions'),
                fn (): array => $this->reactions
                    ->countBy('reaction_type')
                    ->sortDesc()
                    ->keys()
                    ->all(),
            ),
            /*
             * The reader's own reaction, or null where they have not left one.
             *
             * Null rather than absent when the relation was loaded and found
             * nothing: "I looked, there is none" is a different answer from "I
             * did not look", and the client draws a different thing for each.
             */
            'viewer_reaction' => $this->when(
                $this->relationLoaded('viewerReaction'),
                fn (): ?string => $this->viewerReaction?->reaction_type,
            ),
            'author' => new UserSummaryResource($this->whenLoaded('user')),
        ];
    }
}
