<?php

namespace App\Concerns;

use Spatie\MediaLibrary\HasMedia;

/**
 * Shared by the models that carry one photo per collection and hand its
 * address out under a name of their own — `avatar_url`, `cover_url`,
 * `image_url`.
 *
 * @phpstan-require-implements HasMedia
 */
trait ServesMediaUrls
{
    /**
     * The address of the one file in a collection, or null where there is none.
     *
     * Worked out from the disk on the way out rather than kept in a column.
     * A stored URL is a copy of a decision made when it was written, and it
     * goes stale the moment the bucket or the CDN in front of it changes —
     * which is the whole reason these are not columns any more.
     *
     * Passed through `url` so the address is absolute whatever the disk is
     * configured to say; an address already absolute comes back untouched.
     */
    protected function mediaUrl(string $collection): ?string
    {
        $url = $this->getFirstMediaUrl($collection);

        return $url === '' ? null : url($url);
    }
}
