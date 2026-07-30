<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveCircleMember
{
    /**
     * Turn somebody out of a circle.
     *
     * @return bool Whether they were in it to begin with.
     *
     * @throws ValidationException When asked to remove the owner.
     */
    public function execute(Circle $circle, User $user): bool
    {
        $this->refuseOwner($circle, $user);

        return DB::transaction(function () use ($circle, $user): bool {
            $deleted = $circle->memberships()->where('user_id', $user->getKey())->delete();

            if ($deleted === 0) {
                return false;
            }

            // Guarded rather than blind: a count that had drifted below the
            // rows it stands for would take the column negative.
            if ($circle->members_count > 0) {
                $circle->decrement('members_count');
            }

            return true;
        });
    }

    /**
     * The owner stays.
     *
     * A circle whose owner has been turned out is one nobody can manage, so
     * this is refused rather than quietly ignored.
     *
     * @throws ValidationException
     */
    public function refuseOwner(Circle $circle, User $user, string $verb = 'removed'): void
    {
        if ($circle->owner_id === $user->getKey()) {
            throw ValidationException::withMessages([
                'user' => "The owner cannot be {$verb} from their own circle.",
            ]);
        }
    }
}
