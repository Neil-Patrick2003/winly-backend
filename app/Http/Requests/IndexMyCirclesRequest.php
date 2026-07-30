<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMyCirclesRequest extends FormRequest
{
    /**
     * The slices of "my circles" the tabs offer.
     *
     * @var list<string>
     */
    public const STATES = ['all', 'active', 'quiet'];

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
     * Nested under `filter` because that is where the query builder reads them
     * from. Validating them here means an unknown state is turned away before
     * it reaches the query rather than quietly ignored by it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.state' => ['nullable', 'string', Rule::in(self::STATES)],
            'filter.search' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Get the custom validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'filter.state' => 'filter',
            'filter.search' => 'search',
        ];
    }

    /**
     * The tab open, with the default filled in.
     */
    public function filter(): string
    {
        $state = $this->string('filter.state')->value();

        return in_array($state, self::STATES, strict: true) ? $state : 'all';
    }

    /**
     * What was typed into the search box, if anything.
     */
    public function search(): ?string
    {
        $search = trim($this->string('filter.search')->value());

        return $search === '' ? null : $search;
    }
}
