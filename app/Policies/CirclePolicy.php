<?php

namespace App\Policies;

use App\Models\Circle;
use App\Models\User;

class CirclePolicy
{
    /**
     * Let staff into every circle, whatever is being asked.
     *
     * Admin is not a bigger version of owner — it answers a different question.
     * An owner is asked about their own group; an admin is asked about the
     * platform, and the whole point of the staff screens is reaching a circle
     * nobody has asked them into: one whose owner has gone quiet, or that has
     * been reported.
     *
     * Returning null rather than false for everybody else leaves the ordinary
     * checks below to decide, which is what keeps this from being the only
     * rule that matters.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /**
     * Determine whether the user can look inside the circle.
     *
     * Public circles are open to anyone signed in — that is what public means,
     * and the members list is the circle. A private one is its members' own, so
     * it answers only to them and to whoever made it.
     */
    public function view(User $user, Circle $circle): bool
    {
        if (! $circle->is_private) {
            return true;
        }

        return $this->own($user, $circle)
            || $circle->memberships()->where('user_id', $user->getKey())->exists();
    }

    /**
     * Determine whether the user can change the circle.
     */
    public function update(User $user, Circle $circle): bool
    {
        return $this->own($user, $circle);
    }

    /**
     * Determine whether the user can ask people into the circle.
     *
     * Any member may, not the owner alone: a circle grows by the people in it
     * bringing the people they know, and a group where only one person can do
     * the asking stops growing the moment they lose interest.
     */
    public function invite(User $user, Circle $circle): bool
    {
        return $this->own($user, $circle)
            || $circle->memberships()->where('user_id', $user->getKey())->exists();
    }

    /**
     * Determine whether the user can remove or bar other members.
     *
     * The owner alone. Turning members on each other is how a group falls
     * apart, and there is no second rank to delegate it to yet.
     */
    public function manage(User $user, Circle $circle): bool
    {
        return $this->own($user, $circle);
    }

    /**
     * Determine whether the user can take the circle down.
     */
    public function delete(User $user, Circle $circle): bool
    {
        return $this->own($user, $circle);
    }

    /**
     * Whether this user made the circle.
     *
     * A circle seeded before ownership existed has no owner, and an absent
     * owner is nobody rather than everybody — otherwise the first person to ask
     * would inherit it.
     */
    /**
     * Whether this circle answers to this person as its owner.
     *
     * A sub-circle answers to two: whoever runs it, and whoever runs the circle
     * it sits inside. The outer circle's owner made it and chose who keeps it,
     * so they outrank that choice — they can rename it, move its members, hand
     * it to somebody else or close it.
     *
     * Written here rather than at each ability so that every rule leaning on
     * ownership picks it up at once. One level only, so this is a single hop
     * and never a walk up a chain.
     */
    protected function own(User $user, Circle $circle): bool
    {
        if ($circle->owner_id !== null && $circle->owner_id === $user->getKey()) {
            return true;
        }

        return $circle->parent_id !== null
            && $circle->parent()->whereKey($circle->parent_id)
                ->where('owner_id', $user->getKey())
                ->exists();
    }
}
