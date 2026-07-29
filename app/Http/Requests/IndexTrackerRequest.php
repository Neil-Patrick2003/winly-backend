<?php

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTrackerRequest extends FormRequest
{
    /**
     * The stretches of time the tracker can be read over.
     *
     * Presets rather than two date pickers: the question people bring to a
     * tracker is "how have we been doing lately", and picking a fortnight in
     * March is not something anybody asks of it.
     *
     * @var list<string>
     */
    public const RANGES = ['7', '30', '90', 'all'];

    /**
     * The stretch used when the caller does not say.
     */
    public const DEFAULT_RANGE = '30';

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
            'range' => ['nullable', 'string', Rule::in(self::RANGES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The range asked for, with the default filled in.
     */
    public function range(): string
    {
        $range = $this->string('range')->value();

        return in_array($range, self::RANGES, strict: true) ? $range : self::DEFAULT_RANGE;
    }

    /**
     * The first day counted, or null when counting from the beginning.
     *
     * Whole days rather than a rolling window of hours, so a win logged this
     * morning and one logged last night both count as today — which is how a
     * streak counts them too. The range is inclusive of today, so seven days
     * means today and the six before it.
     */
    public function since(): ?CarbonInterface
    {
        return match ($this->range()) {
            '7' => today()->subDays(6),
            '30' => today()->subDays(29),
            '90' => today()->subDays(89),
            default => null,
        };
    }
}
