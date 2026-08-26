<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SetCircleMemberRole
{
    /**
     * Give somebody in the circle the run of it, or take it back.
     *
     * An owner made this way is an owner outright: everything the policy allows
     * the person who made the circle, it allows them. There is no lesser rank
     * to hand out — a rank that could invite but not remove would leave its
     * holder still having to go and find the founder, which is the problem a
     * second owner exists to solve.
     *
     * What it does not move is `circles.owner_id`. That column names whoever
     * made the circle: they cannot be turned out of it, a handover is what
     * moves it, and this deliberately cannot dislodge them — otherwise a
     * second owner could demote the first and take the circle.
     *
     * @throws ValidationException When they are not in the circle, or when
     *                             asked to demote the founder.
     */
    public function execute(Circle $circle, User $user, string $role): CircleMembership
    {
        if (! in_array($role, CircleMembership::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => __('That is not a rank a circle hands out.'),
            ]);
        }

        $membership = $circle->memberships()
            ->where('user_id', $user->getKey())
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user' => __('Only somebody already in the circle can be given the run of it.'),
            ]);
        }

        /*
         * The founder keeps their rank.
         *
         * Refused rather than quietly ignored: a second owner clicking this
         * would otherwise appear to have taken the circle, and be told nothing
         * about why the founder still runs it. Handing the circle over is the
         * separate, deliberate step for that.
         */
        if ($circle->owner_id === $user->getKey() && $role !== CircleMembership::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'user' => __('The circle belongs to them. Hand it to somebody else instead.'),
            ]);
        }

        $membership->update(['role' => $role]);

        return $membership;
    }
}
