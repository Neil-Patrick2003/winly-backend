<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Left open: whether this member may invite is a question about the circle,
     * and the policy answers it in the controller.
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
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                // Inviting yourself is not a thing to be politely ignored: the
                // button that sent it should not have been there.
                Rule::notIn([$this->user()?->getKey()]),
            ],
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
            'user_id.not_in' => 'You are already in this circle.',
            'user_id.exists' => 'That person is no longer around.',
        ];
    }
}
