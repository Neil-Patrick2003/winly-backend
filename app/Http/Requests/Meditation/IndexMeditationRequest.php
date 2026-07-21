<?php

namespace App\Http\Requests\Meditation;

use App\Models\Meditation;
use App\Models\MeditationCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMeditationRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', Rule::exists(MeditationCategory::class, 'id')],
            'sort' => ['nullable', 'string', Rule::in(Meditation::SORTABLE_COLUMNS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'min_duration' => ['nullable', 'integer', 'min:0', 'max:'.Meditation::MAX_DURATION_MINUTES],
            'max_duration' => ['nullable', 'integer', 'min:0', 'max:'.Meditation::MAX_DURATION_MINUTES, 'gte:min_duration'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * Get the filters to apply to the query, with defaults filled in.
     *
     * @return array{search: string|null, category_id: int|null, sort: string, direction: string, min_duration: int|null, max_duration: int|null, from: string|null, to: string|null, per_page: int}
     */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? trim($this->string('search')->value()) : null,
            'category_id' => $this->filled('category_id') ? (int) $this->input('category_id') : null,
            'sort' => (string) $this->input('sort', 'title'),
            'direction' => (string) $this->input('direction', 'asc'),
            'min_duration' => $this->filled('min_duration') ? (int) $this->input('min_duration') : null,
            'max_duration' => $this->filled('max_duration') ? (int) $this->input('max_duration') : null,
            'from' => $this->filled('from') ? (string) $this->input('from') : null,
            'to' => $this->filled('to') ? (string) $this->input('to') : null,
            'per_page' => (int) $this->input('per_page', self::PER_PAGE_OPTIONS[0]),
        ];
    }
}
