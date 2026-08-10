<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Actions\ConsumePasswordResetCode;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * How many wrong codes may be offered for one address before it is locked.
     *
     * Six digits is a million possibilities, which is only out of reach while
     * guessing stays expensive. Five tries per address per hour, against a code
     * that expires in fifteen minutes, is what puts it out of reach.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_MINUTES = 60;

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
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => $this->passwordRules(),
            'device_name' => ['required', 'string', 'max:255'],
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
            'code.digits' => 'The code is six digits.',
        ];
    }

    /**
     * Resolve the user whose code this is, spending the code in the process.
     *
     * An unknown address, a wrong code and an expired one all come back as the
     * same message against `code`. Saying which would answer, for free, the
     * question the reset flow is careful everywhere else not to answer: whether
     * there is an account on that address at all.
     *
     * @throws ValidationException
     */
    public function resolveUser(ConsumePasswordResetCode $consume): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::firstWhere('email', $this->string('email')->value());

        if (! $user || ! $consume->handle($user, $this->string('code')->value())) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_MINUTES * 60);

            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired. Ask for a new one.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure too many codes have not already been guessed for this address.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'code' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ])->status(429);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Keyed on the address as well as the caller, so that moving to another
     * network does not hand back a fresh set of guesses at the same account.
     */
    protected function throttleKey(): string
    {
        return 'password-reset|'.Str::transliterate(
            Str::lower($this->string('email')->value()).'|'.$this->ip()
        );
    }
}
