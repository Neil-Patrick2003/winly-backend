<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Block somebody, and undo what tied the two people together.
 *
 * Follows go both ways, with the counters on each side wound back. Leaving
 * them would keep the blocked person's stories in the blocker's rail and the
 * other way round, since the story rail is built from who you follow.
 * Unblocking does not bring the follows back.
 */
class BlockUser
{
    public function execute(User $blocker, User $blocked): void
    {
        DB::transaction(function () use ($blocker, $blocked): void {
            $blocker->blockedUsers()->syncWithoutDetaching([$blocked->getKey()]);

            $this->unfollow($blocker, $blocked);
            $this->unfollow($blocked, $blocker);
        });
    }

    protected function unfollow(User $follower, User $followee): void
    {
        $removed = DB::table('follows')
            ->where('follower_id', $follower->getKey())
            ->where('followee_id', $followee->getKey())
            ->delete();

        if ($removed === 0) {
            return;
        }

        User::query()->whereKey($follower->getKey())->where('following_count', '>', 0)->decrement('following_count');
        User::query()->whereKey($followee->getKey())->where('followers_count', '>', 0)->decrement('followers_count');
    }
}
