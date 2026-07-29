<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Post;
use App\Models\WinMedia;
use App\Models\WinMeditation;
use App\Rules\MediaFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
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
     * A post carries one or more wins. Each kind may appear only once, since
     * the detail tables hold a single row per post, and the fields a win needs
     * depend on the type that entry declared. Photos and clips hang off the
     * individual win rather than the post.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:2000'],

            'wins' => ['required', 'array', 'min:1', 'max:'.count(Post::WIN_TYPES)],
            'wins.*.type' => ['required', 'string', Rule::in(Post::WIN_TYPES), 'distinct'],
            'wins.*.completed_at' => ['nullable', 'date', 'before_or_equal:now'],

            // Uploaded files, one array entry each. The kind is read from the
            // file itself rather than trusted from the caller.
            'wins.*.media' => ['nullable', 'array', 'max:'.WinMedia::MAX_PER_WIN],
            'wins.*.media.*' => ['required', new MediaFile],

            'wins.*.duration_minutes' => [
                'required_if:wins.*.type,meditation',
                'nullable',
                'integer',
                'min:1',
                'max:'.WinMeditation::MAX_DURATION_MINUTES,
            ],
            'wins.*.completed' => ['nullable', 'boolean'],

            'wins.*.learned_text' => ['required_if:wins.*.type,learning', 'nullable', 'string', 'max:2000'],
            'wins.*.reference_source' => ['nullable', 'string', 'max:2048'],

            // Free text: whatever label the mobile client sends is stored as-is.
            // The length cap only keeps an overlong value from reaching the
            // column and failing as a database error instead of a 422.
            'wins.*.movement_type' => ['nullable', 'string', 'max:255'],
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
            'wins.required' => 'A post needs at least one win.',
            'wins.*.type.distinct' => 'Each kind of win can only appear once on a post.',
            'wins.*.duration_minutes.required_if' => 'Tell us how long you sat for.',
            'wins.*.learned_text.required_if' => 'Tell us what you learned.',
        ];
    }
}
