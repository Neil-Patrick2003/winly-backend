<?php

namespace App\Http\Requests\Meditation;

use App\Concerns\MeditationValidationRules;
use App\Models\MeditationItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeditationRequest extends FormRequest
{
    use MeditationValidationRules;

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
            'title' => trim((string) $this->input('title')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MeditationItem $meditation */
        $meditation = $this->route('meditation');

        return $this->meditationRules($meditation->id);
    }
}
