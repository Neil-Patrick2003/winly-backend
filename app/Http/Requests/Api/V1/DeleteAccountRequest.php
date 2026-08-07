<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeleteAccountRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the password belongs to the account being deleted.
     *
     * Asked for even though the request already carries a valid token, because
     * this is the one action nothing undoes. A phone left unlocked on a table
     * is enough to sign in as somebody; it should not be enough to erase them.
     *
     * Laravel's `current_password` rule is not used here: it checks against the
     * default guard, which is the session, and these requests authenticate with
     * a bearer token instead.
     *
     * @throws ValidationException
     */
    public function ensurePasswordIsCorrect(): void
    {
        if (! Hash::check($this->string('password')->value(), $this->user()->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }
    }
}
