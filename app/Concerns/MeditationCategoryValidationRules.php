<?php

namespace App\Concerns;

use App\Models\MeditationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait MeditationCategoryValidationRules
{
    /**
     * Get the validation rules used to validate meditation categories.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function categoryRules(?int $categoryId = null): array
    {
        return [
            'name' => $this->nameRules($categoryId),
            'icon' => $this->iconRules(),
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation rules used to validate category names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(?int $categoryId = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            $categoryId === null
                ? Rule::unique(MeditationCategory::class)
                : Rule::unique(MeditationCategory::class)->ignore($categoryId),
        ];
    }

    /**
     * Get the validation rules used to validate category icons.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function iconRules(): array
    {
        return ['required', 'string', Rule::in(MeditationCategory::ICONS)];
    }
}
