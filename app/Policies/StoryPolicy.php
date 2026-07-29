<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    /**
     * Determine whether the user can delete the story.
     *
     * A story is nobody's to take down but the person who posted it.
     */
    public function delete(User $user, Story $story): bool
    {
        return $user->id === $story->user_id;
    }

    /**
     * Determine whether the user can see who has watched the story.
     *
     * Only the poster. Watching something is a quieter act than posting it:
     * people expect the author to know they stopped by, and expect that to be
     * where it ends. Handing the same list to the rest of the audience would
     * publish a record of who watches whom that nobody agreed to.
     */
    public function viewers(User $user, Story $story): bool
    {
        return $user->id === $story->user_id;
    }
}
