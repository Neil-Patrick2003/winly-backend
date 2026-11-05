<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Story;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remove a person and everything they put into the app, for good.
 *
 * Both app stores require that an account deleted from inside the app takes
 * its data with it, so this is a real delete rather than the soft one the
 * model would otherwise do. Almost everything hangs off the user with a
 * cascading foreign key — posts and their wins, comments, likes, follows,
 * stories, habits, notifications, memberships — so the row going takes the
 * rest of the database with it.
 *
 * Three things the cascade cannot do, and which this does first:
 *
 * - Put other people's counters right. A follow, a like or a comment going
 *   away under a cascade never touches `followers_count`, `likes_count` and
 *   the rest on the other side, so those are wound back here by hand.
 * - Remove the photos. The rows sit in the polymorphic `media` table with no
 *   key back to the user, and the files on disk are outside the database
 *   entirely. Rows go inside the transaction; files only once it commits,
 *   the same order `PostController::destroy` keeps.
 * - Revoke API tokens and web sessions, which are also keyed loosely.
 *
 * Circles the person created stay, without an owner: other members' posts
 * live there too. `circles.owner_id` is set to null by its own foreign key.
 */
class DeleteAccount
{
    public function execute(User $user): void
    {
        $discarded = [];

        DB::transaction(function () use ($user, &$discarded): void {
            $this->windBackCounters($user);

            $discarded = $this->detachMedia($user);

            $user->tokens()->delete();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $user->forceDelete();
        });

        $filesystem = app(Filesystem::class);

        foreach ($discarded as $file) {
            $filesystem->removeAllFiles($file);
        }
    }

    /**
     * Undo this person's effect on everybody else's numbers.
     *
     * Only what they did to other people's things is counted: their own posts
     * are about to go entirely, so the likes and comments they left on those
     * do not matter.
     */
    protected function windBackCounters(User $user): void
    {
        $id = $user->getKey();

        DB::table('users')
            ->whereIn('id', DB::table('follows')->where('follower_id', $id)->select('followee_id'))
            ->where('followers_count', '>', 0)
            ->decrement('followers_count');

        DB::table('users')
            ->whereIn('id', DB::table('follows')->where('followee_id', $id)->select('follower_id'))
            ->where('following_count', '>', 0)
            ->decrement('following_count');

        PostLike::query()
            ->where('user_id', $id)
            ->whereHas('post', fn (Builder $post) => $post->where('user_id', '!=', $id))
            ->pluck('post_id')
            ->each(fn (string $postId) => Post::query()
                ->whereKey($postId)
                ->where('likes_count', '>', 0)
                ->decrement('likes_count'));

        Comment::query()
            ->where('user_id', $id)
            ->whereHas('post', fn (Builder $post) => $post->where('user_id', '!=', $id))
            ->selectRaw('post_id, count(*) as total')
            ->groupBy('post_id')
            ->toBase()
            ->get()
            ->each(function (object $row): void {
                $total = (int) $row->total;

                // Two queries rather than one clamped expression: SQLite and
                // MySQL disagree on how to spell "no lower than zero".
                $wound = Post::query()
                    ->whereKey($row->post_id)
                    ->where('comments_count', '>=', $total)
                    ->decrement('comments_count', $total);

                if ($wound === 0) {
                    Post::query()->whereKey($row->post_id)->update(['comments_count' => 0]);
                }
            });
    }

    /**
     * Take away the rows for every photo this person owns, keeping the files.
     *
     * Their profile photos, their story images and the photos on each of
     * their wins. Deleted through the query builder so the library's observer
     * does not unlink anything before the transaction has committed.
     *
     * @return list<Media>
     */
    protected function detachMedia(User $user): array
    {
        $posts = Post::query()->where('user_id', $user->getKey())->select('id');

        $owners = [
            $user->getMorphClass() => [$user->getKey()],
            (new Story)->getMorphClass() => Story::query()->where('user_id', $user->getKey())->pluck('id')->all(),
        ];

        foreach ([WinMeditation::class, WinLearning::class, WinMovement::class] as $win) {
            $owners[(new $win)->getMorphClass()] = $win::query()->whereIn('post_id', $posts)->pluck('id')->all();
        }

        $media = Media::query()
            ->where(function (Builder $query) use ($owners): void {
                foreach ($owners as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(fn (Builder $owned) => $owned
                            ->where('model_type', $type)
                            ->whereIn('model_id', $ids));
                    }
                }
            })
            ->get();

        if ($media->isEmpty()) {
            return [];
        }

        Media::query()->whereKey($media->modelKeys())->delete();

        return array_values($media->all());
    }
}
