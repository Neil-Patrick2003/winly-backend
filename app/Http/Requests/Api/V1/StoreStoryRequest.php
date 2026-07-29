<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WinMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoryRequest extends FormRequest
{
    /**
     * The longest caption a story carries.
     */
    public const MAX_CAPTION_LENGTH = 255;

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
     * Photos only: the table holds a single `image_url` with nothing to say
     * what kind of file it is, so a clip would arrive with no way for a client
     * to know to play it rather than show it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimetypes:'.implode(',', WinMedia::IMAGE_MIMES),
                'max:'.WinMedia::MAX_IMAGE_KB,
            ],
            'caption' => ['nullable', 'string', 'max:'.self::MAX_CAPTION_LENGTH],
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
            'image.required' => 'Choose a photo to share.',
            'image.mimetypes' => 'A story has to be a JPEG, PNG, WebP or HEIC photo.',
        ];
    }
}
