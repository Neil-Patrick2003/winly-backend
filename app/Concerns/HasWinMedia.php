<?php

namespace App\Concerns;

use App\Models\WinMedia;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Shared by the three win detail models, each of which may carry photos or
 * clips of its own.
 */
trait HasWinMedia
{
    /**
     * The photos and clips attached to this win, in the order they were sent.
     *
     * @return MorphMany<WinMedia, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(WinMedia::class, 'win')->orderBy('position');
    }
}
