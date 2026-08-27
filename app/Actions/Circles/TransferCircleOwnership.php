<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferCircleOwnership
{
    /**
     * Hand a circle to somebody already in it.
     *
     * Whoever held it becomes an ordinary member, and the person named takes on
     * everything the policy hangs off ownership — renaming the circle,
     * inviting, removing, blocking, deleting it.
     *
     * A handover is not a promotion, so it clears the ranks rather than adding
     * to them: everybody who was running the circle stops, and the person named
     * starts. Somebody who should keep the run of it is given it back
     * afterwards, which is a decision the new owner is now the one to make.
     *
     * The old owner keeps their membership. Handing over the running of a
     * group is not the same as leaving it, and turning them out would lose
     * their place in a circle they may well have built.
     *
     * @throws ValidationException When the person is not in the circle.
     */
    public function execute(Circle $circle, User $owner): Circle
    {
        $membership = $circle->memberships()
            ->where('user_id', $owner->getKey())
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'owner_id' => __('A circle can only be handed to somebody already in it.'),
            ]);
        }

        return DB::transaction(function () use ($circle, $owner, $membership): Circle {
            $circle->memberships()
                ->where('role', CircleMembership::ROLE_OWNER)
                ->update(['role' => CircleMembership::ROLE_MEMBER]);

            $membership->update(['role' => CircleMembership::ROLE_OWNER]);

            $circle->update(['owner_id' => $owner->getKey()]);

            return $circle;
        });
    }
}
