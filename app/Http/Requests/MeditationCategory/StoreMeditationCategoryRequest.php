<?php

namespace App\Http\Requests\MeditationCategory;

use App\Concerns\MeditationCategoryValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreMeditationCategoryRequest extends FormRequest
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
     *
     * The slug is never typed by hand; it always follows the label.
     */
    protected function prepareForValidation(): void
    {
        $label = trim((string) $this->input('label'));

        $this->merge([
            'label' => $label,
            'slug' => Str::slug($label),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->categoryRules();
    }
}
