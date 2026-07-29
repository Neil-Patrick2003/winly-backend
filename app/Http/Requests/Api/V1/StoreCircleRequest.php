<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleRequest extends FormRequest
{
    /** The longest name a circle may carry. */
    public const MAX_NAME_LENGTH = 60;

    /** The longest description. */
    public const MAX_DESCRIPTION_LENGTH = 500;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Unique because the name is how people refer to a circle out loud;
            // two called the same thing is two nobody can tell apart.
            'name' => [
                'required',
                'string',
                'max:'.self::MAX_NAME_LENGTH,
                Rule::unique('circles', 'name'),
            ],
            'description' => ['nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'tag' => ['nullable', 'string', 'max:40'],
            /*
             * Only public circles for now.
             *
             * The column and the flag exist, so the shape of the request is
             * already right; what is missing is everything private implies —
             * the subscription that unlocks it and the features it buys. Until
             * that lands, asking for one is refused rather than quietly
             * downgraded, so a client is never told it made something it did
             * not.
             */
            'is_private' => ['nullable', 'boolean', Rule::in([false, 0, '0'])],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A circle already goes by that name.',
            'is_private.in' => 'Private circles are not available yet.',
        ];
    }
}
