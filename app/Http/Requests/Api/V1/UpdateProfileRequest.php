<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * The profile fields a caller may write.
     *
     * `is_private` is handled apart from these because it needs casting, and
     * the columns missing from the list — the counters, the streak, `is_admin`
     * — are missing on purpose: they are earned or granted, never declared.
     *
     * @var list<string>
     */
    protected const EDITABLE = ['full_name', 'username', 'email', 'bio', 'cover_gradient'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route edits whoever is signed in, so there is no one else to be
     * authorized against; `auth:sanctum` has already done the work.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Every field is `sometimes`: this is a partial edit, and a screen that
     * only changes the bio must not have to resend the name to keep it. The
     * `required` inside each set still applies to fields that were sent, so
     * `full_name: ""` is rejected rather than quietly blanking the profile.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'full_name' => ['sometimes', ...$this->fullNameRules()],
            'username' => ['sometimes', ...$this->usernameRules($userId)],
            'email' => ['sometimes', ...$this->emailRules($userId)],
            'bio' => ['sometimes', ...$this->bioRules()],
            'cover_gradient' => ['sometimes', ...$this->coverGradientRules()],
            'is_private' => ['sometimes', 'boolean'],
            'avatar' => $this->avatarRules(),
            'remove_avatar' => ['sometimes', 'boolean'],
            'cover' => $this->coverPhotoRules(),
            'remove_cover' => ['sometimes', 'boolean'],
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
            'username.regex' => 'A username can only use lowercase letters, numbers and underscores.',
            'username.unique' => 'That username is already taken.',
            'avatar.mimetypes' => 'A profile photo has to be a JPEG, PNG, WebP or HEIC image.',
            'cover.mimetypes' => 'A cover photo has to be a JPEG, PNG, WebP or HEIC image.',
        ];
    }

    /**
     * The profile columns to write, typed, and limited to what was sent.
     *
     * Booleans arrive as "1" or "true" over multipart, so `is_private` is read
     * back through the request rather than taken from the validated array.
     *
     * @return array<string, mixed>
     */
    public function profileChanges(): array
    {
        $changes = $this->safe()->only(self::EDITABLE);

        if ($this->has('is_private')) {
            $changes['is_private'] = $this->boolean('is_private');
        }

        return $changes;
    }

    /**
     * Whether the caller asked for their current photo to be dropped.
     */
    public function removesAvatar(): bool
    {
        return $this->boolean('remove_avatar');
    }

    /**
     * Whether the caller asked for their current cover photo to be dropped.
     *
     * Taking the photo down does not clear `cover_gradient`: the gradient is
     * what shows underneath, so removing the photo reveals it again rather than
     * leaving the header blank.
     */
    public function removesCover(): bool
    {
        return $this->boolean('remove_cover');
    }
}
