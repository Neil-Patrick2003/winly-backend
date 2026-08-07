<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => mb_strtolower(trim((string) $this->input('username'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'username' => $this->usernameRules(),
            'password' => $this->passwordRules(),
            'device_name' => ['required', 'string', 'max:255'],
            /*
             * `accepted` rather than `boolean`: it passes only for a true-ish
             * value and fails for a false one, so an account cannot be made by
             * sending the field set to false. The client gates the button on
             * the same thing, but the client is not what decides it.
             */
            'terms_accepted' => ['accepted'],
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
            'username.regex' => 'The username may only contain lowercase letters, numbers, and underscores.',
            'terms_accepted.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
        ];
    }
}
