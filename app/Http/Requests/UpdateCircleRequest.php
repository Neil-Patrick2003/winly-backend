<?php

namespace App\Http\Requests;

use App\Http\Requests\Api\V1\StoreCircleRequest;
use App\Models\Circle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCircleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is settled by the policy in the controller, which can tell a
     * circle that is not there from one that is not theirs.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The same shape a circle is created with, less the fields that cannot
     * change after the fact. The name stays unique, ignoring this circle so
     * saving the form without touching the name is not a clash with itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Circle $circle */
        $circle = $this->route('circle');

        return [
            'name' => [
                'required',
                'string',
                'max:'.StoreCircleRequest::MAX_NAME_LENGTH,
                Rule::unique('circles', 'name')->ignore($circle->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:'.StoreCircleRequest::MAX_DESCRIPTION_LENGTH],
            'tag' => ['nullable', 'string', 'max:40'],
            'icon_initial' => ['required', 'string', 'max:2'],
            'color_hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
            'color_hex.regex' => 'Use a six digit hex colour, like #4F46E5.',
        ];
    }
}
