<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can edit the comment.
     *
     * Only the commenter may reword what they said; nobody gets to put words
     * in somebody else's mouth, the post's author included.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * Determine whether the user can delete the comment.
     *
     * The commenter can retract it, and the post's author can clear it off
     * their own post.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->id === $comment->post->user_id;
    }
}
