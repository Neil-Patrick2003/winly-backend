<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MeditationCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MeditationCategory
 */
class MeditationCategoryResource extends JsonResource
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
            'label' => $this->label,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'description' => $this->description,
            'meditation_items_count' => (int) ($this->meditation_items_count ?? 0),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
