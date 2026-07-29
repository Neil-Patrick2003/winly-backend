<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\CircleBlock;
use App\Models\CircleInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BlockFromCircle
{
    public function __construct(protected RemoveCircleMember $removeMember) {}

    /**
     * Bar somebody from a circle.
     *
     * Removing takes back this membership; blocking stops the next one. So this
     * does both, and cancels any invitation still standing — otherwise a
     * blocked person could walk back in through a message sent before.
     *
     * @param  User  $actor  Whoever is doing the blocking, kept for the record.
     *
     * @throws ValidationException When asked to block the owner.
     */
    public function execute(Circle $circle, User $user, User $actor): void
    {
        $this->removeMember->refuseOwner($circle, $user, verb: 'blocked');

        DB::transaction(function () use ($circle, $user, $actor): void {
            CircleBlock::firstOrCreate(
                ['circle_id' => $circle->getKey(), 'user_id' => $user->getKey()],
                ['blocked_by' => $actor->getKey()],
            );

            $this->removeMember->execute($circle, $user);

            $circle->invitations()
                ->where('invitee_id', $user->getKey())
                ->pending()
                ->update([
                    'status' => CircleInvitation::DECLINED,
                    'responded_at' => now(),
                ]);
        });
    }

    /**
     * Stop keeping somebody out.
     *
     * Unblocking does not rejoin them; it only clears the bar.
     */
    public function undo(Circle $circle, User $user): void
    {
        $circle->blocks()->where('user_id', $user->getKey())->delete();
    }
}
