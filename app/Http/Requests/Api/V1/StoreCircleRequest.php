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
             * The circle this one sits inside, when it is one of those.
             *
             * Narrowed to circles the caller owns: a sub-circle is the parent
             * owner's to make, and being handed the id of somebody else's group
             * is not permission to open a circle inside it.
             *
             * The controller refuses a parent that is itself a sub-circle. That
             * is the one-level rule, and it is checked there rather than here
             * because it is a fact about the circle rather than about the shape
             * of the request.
             */
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('circles', 'id')->where('owner_id', $this->user()?->getKey()),
            ],
            /*
             * Who may find it.
             *
             * Public is the default, and absent means public: every circle made
             * before this field existed is one, and a client that does not know
             * to send it must keep making the kind it has always made.
             *
             * A private circle is left out of Discover and out of search, so
             * the only ways in are an invitation and a link from somebody
             * already inside.
             */
            'is_private' => ['nullable', 'boolean'],
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
