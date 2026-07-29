<?php

namespace App\Policies;

use App\Models\Circle;
use App\Models\User;

class CirclePolicy
{
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
    protected function own(User $user, Circle $circle): bool
    {
        return $circle->owner_id !== null && $circle->owner_id === $user->getKey();
    }
}
