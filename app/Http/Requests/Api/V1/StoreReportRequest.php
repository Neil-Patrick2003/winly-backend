<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    /** What was reported, once `after` has found it. */
    public ?Model $target = null;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Report::TYPES))],
            'id' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', Rule::in(array_keys(Report::REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Find what is being reported, and refuse reporting your own things.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var class-string<Model> $class */
                $class = Report::TYPES[$this->string('type')->value()];

                $this->target = $class::query()->find($this->string('id')->value());

                if ($this->target === null) {
                    $validator->errors()->add('id', 'That has already been removed.');

                    return;
                }

                $ownerId = $this->target instanceof User
                    ? $this->target->getKey()
                    : $this->target->getAttribute('user_id');

                if ($ownerId === $this->user()->getKey()) {
                    $validator->errors()->add('id', 'You cannot report your own content.');
                }
            },
        ];
    }
}
