<?php

namespace App\Concerns;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared by the three win detail models, each of which may carry photos or
 * clips of its own.
 *
 * The files are kept by the media library rather than in a table of this
 * application's own, so what is left here is the one collection a win has and
 * the two things the rest of the code does with it.
 */
trait HasWinMedia
{
    use InteractsWithMedia;

    /**
     * The collection a win's photos and clips are kept in.
     */
    public const MEDIA_COLLECTION = 'win';

    /**
     * Register that collection.
     *
     * No disk is named, so it takes the media library's own — `MEDIA_DISK`,
     * which is a setting of its own and not the application's default disk.
     * Where a win's files live is a deployment's decision, and naming a disk
     * here would be this application overruling it.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION);
    }

    /**
     * The photos and clips on this win, in the order they were added.
     *
     * @return MediaCollection<int, Media>
     */
    public function winMedia(): MediaCollection
    {
        return $this->getMedia(self::MEDIA_COLLECTION);
    }

    /**
     * Put one uploaded file on the end of the run.
     *
     * Where it lands is the library's to decide — it numbers a new file past
     * the highest already there, which is what "on the end" means.
     */
    public function addWinMedia(UploadedFile $file): Media
    {
        /*
         * The type is taken from the upload rather than left as the library
         * read it back off the stored copy. On a real file the two agree, both
         * being the same guess against the same bytes. Where they can drift
         * this is the one the request was let in on, and a win rendered from a
         * type its own validation never saw could report a clip as neither a
         * photo nor a clip.
         *
         * Read *before* the file is handed over, and this order matters: adding
         * it moves the upload out of its temporary path and unlinks what was
         * there. Asking afterwards asks about a file that no longer exists, and
         * the answer is not a wrong type but a raised error.
         */
        $declared = $file->getMimeType();

        $media = $this->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);

        if ($declared !== null && $declared !== $media->mime_type) {
            $media->mime_type = $declared;
            $media->save();
        }

        return $media;
    }
}
