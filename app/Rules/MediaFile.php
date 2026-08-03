<?php

namespace App\Rules;

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
 *
 * This is also where the application says what a media file may be at all.
 * The limits used to hang off the model that stored them, which left stories
 * and profile photos reaching into a win's model to ask what an image is; they
 * sit on the rule now because that question is about the upload, not about
 * whichever thing ends up holding it.
 */
class MediaFile implements ValidationRule
{
    /**
     * The kinds of file the application accepts.
     *
     * @var list<string>
     */
    public const KINDS = ['image', 'video'];

    /**
     * The photo formats accepted, by mime type.
     *
     * @var list<string>
     */
    public const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    /**
     * The clip formats accepted, by mime type.
     *
     * @var list<string>
     */
    public const VIDEO_MIMES = [
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * The largest photo accepted, in kilobytes.
     */
    public const MAX_IMAGE_KB = 10240;

    /**
     * The largest clip accepted, in kilobytes.
     */
    public const MAX_VIDEO_KB = 102400;

    /**
     * The most files one win may carry.
     */
    public const MAX_PER_WIN = 10;

    /**
     * The kind a given mime type maps to, or null when it is not accepted.
     */
    public static function kindForMime(?string $mime): ?string
    {
        return match (true) {
            in_array($mime, self::IMAGE_MIMES, strict: true) => 'image',
            in_array($mime, self::VIDEO_MIMES, strict: true) => 'video',
            default => null,
        };
    }

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

        $kind = self::kindForMime($value->getMimeType());

        if ($kind === null) {
            $fail('The :attribute must be a JPEG, PNG, WebP, HEIC, MP4, MOV or WebM file.');

            return;
        }

        $maxKb = $kind === 'video' ? self::MAX_VIDEO_KB : self::MAX_IMAGE_KB;

        if ($value->getSize() > $maxKb * 1024) {
            $fail(sprintf(
                'The :attribute may not be larger than %d MB.',
                intdiv($maxKb, 1024),
            ));
        }
    }
}
