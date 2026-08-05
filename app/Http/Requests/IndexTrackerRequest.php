<?php

namespace App\Http\Requests;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexTrackerRequest extends FormRequest
{
    /**
     * How far back the tracker looks when the caller does not say.
     */
    public const DEFAULT_DAYS = 30;

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
            'from' => ['nullable', 'date'],
            // Both bounds are inclusive, so the same day in each is a single
            // day rather than nothing at all.
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],

            /*
             * Which circles to count, out of this one and the circles inside
             * it. Absent means all of them, which is what the tab is for.
             *
             * Only ids are validated here — whether each one actually belongs
             * to this circle is a question about the circle, and the controller
             * answers it by intersecting with what it already knows.
             */
            'circles' => ['nullable', 'array'],
            'circles.*' => ['uuid'],
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
            'to.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }

    /**
     * The circles the reader asked to count, if they narrowed it.
     *
     * @return list<string>
     */
    public function circleIds(): array
    {
        $ids = $this->validated('circles') ?? [];

        return array_values(array_unique(array_map(strval(...), $ids)));
    }

    /**
     * The first day counted.
     *
     * Whole days rather than a rolling window of hours, so a win logged this
     * morning and one logged last night both count for today — which is how a
     * streak counts them too.
     */
    public function from(): CarbonInterface
    {
        return $this->filled('from')
            ? $this->date('from')->startOfDay()
            : today()->subDays(self::DEFAULT_DAYS - 1);
    }

    /**
     * The last day counted, taken to the end of it so a win logged at teatime
     * on the closing day is not left out of its own range.
     */
    public function to(): CarbonInterface
    {
        return $this->filled('to')
            ? $this->date('to')->endOfDay()
            : today()->endOfDay();
    }

    /**
     * How many days the range covers, both ends included.
     */
    public function days(): int
    {
        return (int) $this->from()->startOfDay()->diffInDays($this->to()->startOfDay()) + 1;
    }
}
