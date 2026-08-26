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
     * Whoever runs the circle, which may be more than one person. Turning
     * members on each other is how a group falls apart, so it is not something
     * an ordinary member can do — but a group large enough to need moderating
     * is one where waiting on a single pair of hands is its own way of falling
     * apart.
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
     * Whether this circle answers to this person as one of its owners.
     *
     * A circle answers to whoever made it and to anyone they have since made an
     * owner alongside them. The two are the same rank and this asks one
     * question of both — there is no ability an owner has that a second owner
     * does not.
     *
     * A sub-circle answers further up as well: to whoever runs the circle it
     * sits inside. The outer circle's owners made it and chose who keeps it, so
     * they outrank that choice — they can rename it, move its members, hand it
     * to somebody else or close it.
     *
     * Written here rather than at each ability so that every rule leaning on
     * ownership picks it up at once. One level only, so this is a single hop
     * and never a walk up a chain.
     */
    protected function own(User $user, Circle $circle): bool
    {
        if ($this->runs($user, $circle->owner_id, $circle->getKey())) {
            return true;
        }

        if ($circle->parent_id === null) {
            return false;
        }

        // The rank above is answered off the list already in hand; only the
        // outer circle's `owner_id` is still worth a query, and it is the one
        // this asked for before ranks existed.
        return $user->holdsOwnerRankIn($circle->parent_id)
            || Circle::query()
                ->whereKey($circle->parent_id)
                ->where('owner_id', $user->getKey())
                ->exists();
    }

    /**
     * Whether this person runs one particular circle, leaving any circle it
     * sits inside out of it.
     *
     * A circle seeded before ownership existed has no owner, and an absent
     * owner is nobody rather than everybody — otherwise the first person to ask
     * would inherit it.
     */
    protected function runs(User $user, ?string $ownerId, string $circleId): bool
    {
        return ($ownerId !== null && $ownerId === $user->getKey())
            || $user->holdsOwnerRankIn($circleId);
    }
}
