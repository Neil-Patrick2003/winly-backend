<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransferCircleOwnership
{
    /**
     * Hand a circle to somebody already in it.
     *
     * Ownership is the `owner_id` column and nothing else, so this is one
     * write: whoever held it becomes an ordinary member, and the person named
     * takes on everything the policy hangs off that column — renaming the
     * circle, inviting, removing, blocking, deleting it.
     *
     * The old owner keeps their membership. Handing over the running of a
     * group is not the same as leaving it, and turning them out would lose
     * their place in a circle they may well have built.
     *
     * @throws ValidationException When the person is not in the circle.
     */
    public function execute(Circle $circle, User $owner): Circle
    {
        $isMember = $circle->memberships()
            ->where('user_id', $owner->getKey())
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'owner_id' => __('A circle can only be handed to somebody already in it.'),
            ]);
        }

        $circle->update(['owner_id' => $owner->getKey()]);

        return $circle;
    }
}
