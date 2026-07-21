<?php

namespace App\Http\Requests\MeditationCategory;

use App\Models\MeditationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMeditationCategoryRequest extends FormRequest
{
    /**
     * The row counts a user may choose from.
     *
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [10, 25, 50];

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
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(MeditationCategory::SORTABLE_COLUMNS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * Get the filters to apply to the query, with defaults filled in.
     *
     * @return array{search: string|null, sort: string, direction: string, from: string|null, to: string|null, per_page: int}
     */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? trim($this->string('search')->value()) : null,
            'sort' => (string) $this->input('sort', 'name'),
            'direction' => (string) $this->input('direction', 'asc'),
            'from' => $this->filled('from') ? (string) $this->input('from') : null,
            'to' => $this->filled('to') ? (string) $this->input('to') : null,
            'per_page' => (int) $this->input('per_page', self::PER_PAGE_OPTIONS[0]),
        ];
    }
}
