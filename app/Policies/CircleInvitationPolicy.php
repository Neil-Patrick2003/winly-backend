<?php

namespace App\Policies;

use App\Models\CircleInvitation;
use App\Models\User;

class CircleInvitationPolicy
{
    /**
     * Determine whether the user can answer this invitation.
     *
     * Only the person it was sent to. An invitation is a question put to
     * somebody, and nobody else gets to answer for them — not the inviter, and
     * not the circle's owner.
     */
    public function respond(User $user, CircleInvitation $invitation): bool
    {
        return $invitation->invitee_id === $user->getKey()
            && $invitation->status === CircleInvitation::PENDING;
    }
}
