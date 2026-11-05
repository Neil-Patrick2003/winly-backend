<?php

namespace App\Actions;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Delete a post with its photos and put its author's numbers right.
 *
 * Used by the author deleting their own post and by a moderator taking one
 * down after a report, so the two cannot drift apart.
 *
 * The media rows go inside the transaction and the files only after it has
 * committed: deleting a media model unlinks its file on the spot, and a
 * rollback cannot bring a file back.
 */
class RemovePost
{
    public function __construct(protected RecalculateWinStats $stats) {}

    public function execute(Post $post): void
    {
        $post->loadMissing(['user', 'winMeditation', 'winLearning', 'winMovement']);

        $winIds = array_values(array_filter([
            $post->winMeditation?->getKey(),
            $post->winLearning?->getKey(),
            $post->winMovement?->getKey(),
        ]));

        $media = [];

        DB::transaction(function () use ($post, $winIds, &$media): void {
            $media = Media::query()->whereIn('model_id', $winIds)->get()->all();

            if ($media !== []) {
                Media::query()->whereKey(array_map(fn (Media $file) => $file->getKey(), $media))->delete();
            }

            $post->delete();
        });

        $filesystem = app(Filesystem::class);

        foreach ($media as $file) {
            $filesystem->removeAllFiles($file);
        }

        $this->stats->execute($post->user);
    }
}
