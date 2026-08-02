<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\MediaFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * The longest bio a profile carries.
     */
    public const MAX_BIO_LENGTH = 500;

    /**
     * The longest cover gradient name accepted.
     */
    public const MAX_COVER_GRADIENT_LENGTH = 50;

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?string $userId = null): array
    {
        return [
            'full_name' => $this->fullNameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function fullNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate usernames.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9_]+$/',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user bios.
     *
     * Nullable rather than required: clearing a bio is a thing people do, and
     * an empty one is a valid profile.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function bioRules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_BIO_LENGTH];
    }

    /**
     * Get the validation rules used to validate profile cover gradients.
     *
     * The value is a name the clients pick from their own palette, not a
     * colour, so this checks the shape and leaves the list to them.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function coverGradientRules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_COVER_GRADIENT_LENGTH];
    }

    /**
     * Get the validation rules used to validate an uploaded avatar photo.
     *
     * Photos only, and the same formats a story accepts, so a client that can
     * already send one can send the other.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function avatarRules(): array
    {
        return [
            'nullable',
            'file',
            'mimetypes:'.implode(',', MediaFile::IMAGE_MIMES),
            'max:'.MediaFile::MAX_IMAGE_KB,
        ];
    }

    /**
     * Get the validation rules used to validate an uploaded cover photo.
     *
     * The same rules an avatar gets. A cover is shown far wider than a profile
     * photo, but it is the same kind of file and holding it to a different
     * ceiling would only make one of the two limits a surprise.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function coverPhotoRules(): array
    {
        return $this->avatarRules();
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
