<?php

namespace App\Concerns;

use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MeditationValidationRules
{
    /**
     * Get the validation rules used to validate meditations.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function meditationRules(?string $meditationId = null): array
    {
        return [
            'category_id' => ['required', 'uuid', Rule::exists(MeditationCategory::class, 'id')],
            'title' => $this->titleRules($meditationId),
            'instructions' => ['nullable', 'string', 'max:2000'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'audio_url' => $this->mediaUrlRules(),
            'video_url' => $this->mediaUrlRules(),
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:'.MeditationItem::MAX_DURATION_MINUTES],
        ];
    }

    /**
     * Get the validation rules used to validate meditation titles.
     *
     * Titles only need to be unique within their own category.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function titleRules(?string $meditationId = null): array
    {
        $unique = Rule::unique(MeditationItem::class, 'title')
            ->where('category_id', $this->input('category_id'));

        return [
            'required',
            'string',
            'max:255',
            $meditationId === null ? $unique : $unique->ignore($meditationId),
        ];
    }

    /**
     * Get the validation rules used to validate media links.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function mediaUrlRules(): array
    {
        return ['nullable', 'string', 'url:http,https', 'max:2048'];
    }
}
