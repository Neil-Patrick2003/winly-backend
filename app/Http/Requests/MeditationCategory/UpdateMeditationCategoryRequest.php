<?php

namespace App\Http\Requests\MeditationCategory;

use App\Concerns\MeditationCategoryValidationRules;
use App\Models\MeditationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeditationCategoryRequest extends FormRequest
{
    use MeditationCategoryValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MeditationCategory $category */
        $category = $this->route('meditation_category');

        return $this->categoryRules($category->id);
    }
}
