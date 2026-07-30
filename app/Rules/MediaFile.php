<?php

namespace App\Rules;

use App\Models\WinMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A photo or clip uploaded alongside a win.
 *
 * The size limit depends on what was actually sent, so a 40MB clip passes
 * while a 40MB photo does not. Doing it here rather than in the rule array
 * keeps the per-kind limit from having to be expressed against a wildcard.
 */
class MediaFile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be an uploaded file. Send the post as multipart/form-data.');

            return;
        }

        if (! $value->isValid()) {
            $fail('The :attribute did not finish uploading. Try again.');

            return;
        }

        $kind = WinMedia::kindForMime($value->getMimeType());

        if ($kind === null) {
            $fail('The :attribute must be a JPEG, PNG, WebP, HEIC, MP4, MOV or WebM file.');

            return;
        }

        $maxKb = $kind === 'video' ? WinMedia::MAX_VIDEO_KB : WinMedia::MAX_IMAGE_KB;

        if ($value->getSize() > $maxKb * 1024) {
            $fail(sprintf(
                'The :attribute may not be larger than %d MB.',
                intdiv($maxKb, 1024),
            ));
        }
    }
}
