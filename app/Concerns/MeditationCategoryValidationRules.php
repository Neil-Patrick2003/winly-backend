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
    protected function categoryRules(?string $categoryId = null): array
    {
        return [
            'label' => $this->labelRules($categoryId),
            'slug' => $this->slugRules($categoryId),
            'icon' => $this->iconRules(),
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation rules used to validate category labels.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function labelRules(?string $categoryId = null): array
    {
        $unique = Rule::unique(MeditationCategory::class, 'label');

        return [
            'required',
            'string',
            'max:255',
            $categoryId === null ? $unique : $unique->ignore($categoryId),
        ];
    }

    /**
     * Get the validation rules used to validate category slugs.
     *
     * The slug is derived from the label rather than typed, so this mainly
     * guards against two labels collapsing to the same slug.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function slugRules(?string $categoryId = null): array
    {
        $unique = Rule::unique(MeditationCategory::class, 'slug');

        return [
            'required',
            'string',
            'max:255',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $categoryId === null ? $unique : $unique->ignore($categoryId),
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
