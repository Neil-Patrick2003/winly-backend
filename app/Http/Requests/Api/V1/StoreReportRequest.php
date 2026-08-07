<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreReportRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * The short name of what is being reported, checked against a
             * whitelist. Taking a model class name from the request instead
             * would let a caller point a report at any table in the schema.
             */
            'type' => ['required', 'string', Rule::in(array_keys(Report::REPORTABLE))],
            'id' => ['required', 'uuid'],
            'reason' => ['required', 'string', Rule::in(Report::REASONS)],
            'note' => ['nullable', 'string', 'max:'.Report::MAX_NOTE_LENGTH],
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
            'reason.in' => 'Choose one of the listed reasons.',
        ];
    }

    /**
     * The post, comment, story or person this report is about.
     *
     * Resolved after validation so a report cannot be filed against something
     * that is not there — a deleted post, or an id somebody made up.
     *
     * @throws ValidationException
     */
    public function reportable(): Model
    {
        /** @var class-string<Model> $class */
        $class = Report::REPORTABLE[$this->string('type')->value()];

        $reportable = $class::find($this->string('id')->value());

        if ($reportable === null) {
            throw ValidationException::withMessages([
                'id' => 'That content is no longer available.',
            ]);
        }

        return $reportable;
    }
}
