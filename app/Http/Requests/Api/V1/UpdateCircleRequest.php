<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCircleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Whether this person may change *this* circle is the policy's question,
     * asked in the controller — a form request answering it here would have to
     * reach for the route's model to do it, and would answer it in a different
     * place from every other circle action.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The lengths are `StoreCircleRequest`'s: what a circle may be called does
     * not depend on whether it is being made or renamed, and two copies of the
     * numbers would eventually disagree.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:'.StoreCircleRequest::MAX_NAME_LENGTH,
                // Ignoring itself, or a circle could not be saved without
                // being renamed — its own name is the one it already holds.
                Rule::unique('circles', 'name')->ignore($this->route('circle')),
            ],
            /*
             * Absent leaves the column alone; an explicit `null` clears it.
             *
             * Which is what makes this a PATCH rather than a replacement: a
             * client sending only the name must not silently empty the
             * description it did not mention.
             */
            'description' => ['sometimes', 'nullable', 'string', 'max:'.StoreCircleRequest::MAX_DESCRIPTION_LENGTH],
            'tag' => ['sometimes', 'nullable', 'string', 'max:40'],
            /*
             * Absent leaves it as it is, like the two fields above: a client
             * sending a rename alone must not decide who can find the circle as
             * a side effect of it.
             *
             * Turning a public circle private does not take it back from the
             * people already in it — it only stops the next stranger finding
             * it. What is already on the wall was shared with them.
             */
            'is_private' => ['sometimes', 'boolean'],
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
        ];
    }
}
