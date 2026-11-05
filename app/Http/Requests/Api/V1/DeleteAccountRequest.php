<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting an account asks for the password again.
 *
 * A token on an unlocked phone is not the same as the person: anyone who picks
 * the phone up could otherwise wipe somebody out in two taps.
 */
class DeleteAccountRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
