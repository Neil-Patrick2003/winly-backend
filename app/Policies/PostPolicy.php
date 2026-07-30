<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can change the post.
     *
     * The author alone. A circle owner has no say here: sharing a win into
     * somebody's circle does not hand them the pen, and a moderation tool that
     * silently rewrites what a person said would be a worse thing than the
     * post it was cleaning up.
     */
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    /**
     * Determine whether the user can take the post down.
     *
     * Same rule as changing it, and for the same reason.
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
