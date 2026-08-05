<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can read the post at all.
     *
     * The same three ways in that `Post::visibleTo` gives a list, asked of one
     * post instead: public, their own, or shared into a circle they belong to.
     *
     * This is what every single-post route leans on — reading it, commenting on
     * it, liking it, keeping it. A list can filter; one post has to be asked
     * about, and being handed the id is not the same as being allowed to see
     * what is behind it.
     */
    public function view(User $user, Post $post): bool
    {
        if ($post->isPublic() || $user->id === $post->user_id) {
            return true;
        }

        // The same two ways in that `Post::visibleTo` gives a list: a circle
        // this reader is in, or a sub-circle of one. See the scope for why the
        // second never needs to walk further than a single hop.
        $mine = $user->circles()->getQuery()->select('circles.id');

        return $post->circles()
            ->where(fn ($circles) => $circles
                ->whereIn('circles.id', $mine)
                ->orWhereIn('circles.parent_id', $mine))
            ->exists();
    }

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
