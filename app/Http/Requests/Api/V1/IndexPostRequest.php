<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPostRequest extends FormRequest
{
    /**
     * How many posts a page carries when the caller does not say.
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * The largest page a caller may ask for.
     */
    public const MAX_PER_PAGE = 50;

    /**
     * Which slice of the feed to serve.
     *
     * `all` is everything anybody has shared. `following` narrows to the people
     * the reader chose to follow, and `circles` to what has been shared into
     * circles they are in — not two audiences but two ways of arriving at the
     * same posts, which is why one post can appear under both.
     *
     * @var list<string>
     */
    public const FEEDS = ['all', 'following', 'circles'];

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
            'feed' => ['nullable', 'string', Rule::in(self::FEEDS)],
        ];
    }

    /**
     * Which slice was asked for, defaulting to the whole feed.
     */
    public function feed(): string
    {
        return $this->filled('feed') ? $this->string('feed')->value() : 'all';
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
