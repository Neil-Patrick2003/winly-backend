<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Post;
use App\Models\WinMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
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
            'caption' => $this->caption,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'shares_count' => $this->shares_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => new UserSummaryResource($this->whenLoaded('user')),
            /*
             * The circles it was shared into, so a feed card can say where it
             * came from. Empty on an openly shared post, which belongs nowhere
             * in particular; absent where the caller never looked.
             */
            'circles' => CircleResource::collection($this->whenLoaded('circles')),
            'viewer_has_liked' => $this->relationLoaded('viewerLike')
                ? $this->viewerLike !== null
                : false,
            /*
             * Whether the reader has kept this post.
             *
             * Theirs alone: it says nothing about how many people saved it,
             * because nobody is told that — a save is a private bookmark, not a
             * reaction.
             */
            'viewer_has_saved' => $this->relationLoaded('viewerSave')
                ? $this->viewerSave !== null
                : false,
            'wins' => $this->wins(),
        ];
    }

    /**
     * Every win this post records, in a stable order.
     *
     * The three detail tables are expected to be eager loaded; an unloaded
     * relation is skipped rather than quietly firing a query per post.
     *
     * @return list<array<string, mixed>>
     */
    protected function wins(): array
    {
        $wins = [];

        if ($this->relationLoaded('winMeditation') && $this->winMeditation !== null) {
            $wins[] = [
                ...$this->common($this->winMeditation, 'meditation'),
                'duration_minutes' => $this->winMeditation->duration_minutes,
                'completed' => $this->winMeditation->completed,
            ];
        }

        if ($this->relationLoaded('winLearning') && $this->winLearning !== null) {
            $wins[] = [
                ...$this->common($this->winLearning, 'learning'),
                'learned_text' => $this->winLearning->learned_text,
                'reference_source' => $this->winLearning->reference_source,
            ];
        }

        if ($this->relationLoaded('winMovement') && $this->winMovement !== null) {
            $wins[] = [
                ...$this->common($this->winMovement, 'movement'),
                'movement_type' => $this->winMovement->movement_type,
            ];
        }

        return $wins;
    }

    /**
     * The fields every kind of win carries.
     *
     * @return array<string, mixed>
     */
    protected function common(Model $win, string $type): array
    {
        return [
            'type' => $type,
            'completed_at' => $win->completed_at?->toIso8601String(),
            'media_attached' => $win->media_attached,
            'media' => $this->media($win),
        ];
    }

    /**
     * The photos and clips on one win.
     *
     * @return list<array<string, mixed>>
     */
    protected function media(Model $win): array
    {
        if (! $win->relationLoaded('media')) {
            return [];
        }

        return $win->media
            ->map(fn (WinMedia $file): array => [
                'id' => $file->id,
                'url' => $file->url,
                'kind' => $file->kind,
                'position' => $file->position,
            ])
            ->values()
            ->all();
    }
}
