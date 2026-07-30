<?php

namespace App\Http\Requests\Api\V1;

use App\Models\StoryReaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryReactionRequest extends FormRequest
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
             * One of the set the clients offer, not free text.
             *
             * The column is a string for room to grow, but an open one would
             * let a client invent a reaction no other client can draw.
             */
            'reaction_type' => ['required', 'string', Rule::in(StoryReaction::TYPES)],
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
            'reaction_type.in' => 'That is not one of the reactions you can leave.',
        ];
    }
}
