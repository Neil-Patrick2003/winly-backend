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
}
