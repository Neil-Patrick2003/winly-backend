<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexFollowRequest extends FormRequest
{
    /**
     * How many people a page carries when the caller does not say.
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * The largest page a caller may ask for.
     */
    public const MAX_PER_PAGE = 50;

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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the page size to apply, with the default filled in.
     */
    public function perPage(): int
    {
        return $this->filled('per_page')
            ? $this->integer('per_page')
            : self::DEFAULT_PER_PAGE;
    }
}
