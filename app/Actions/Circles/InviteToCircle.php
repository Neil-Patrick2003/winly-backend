<?php

namespace App\Actions\Circles;

use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class InviteToCircle
{
    /**
     * Ask somebody to join.
     *
     * Re-asking someone who declined replaces their answer rather than adding a
     * second row, so the unique index holds and a circle cannot be used to
     * pester. Asking someone already in, or someone barred, is refused outright
     * — the button that sent it should not have been there.
     *
     * @param  string  $field  The input the error belongs against, which differs
     *                         between the API's payload and the web form.
     *
     * @throws ValidationException
     */
    public function execute(Circle $circle, User $inviter, User $invitee, string $field = 'user_id'): CircleInvitation
    {
        if ($circle->memberships()->where('user_id', $invitee->getKey())->exists()) {
            throw ValidationException::withMessages([
                $field => 'They are already in this circle.',
            ]);
        }

        if ($circle->blocks()->where('user_id', $invitee->getKey())->exists()) {
            throw ValidationException::withMessages([
                $field => 'They are blocked from this circle.',
            ]);
        }

        return CircleInvitation::updateOrCreate(
            ['circle_id' => $circle->getKey(), 'invitee_id' => $invitee->getKey()],
            [
                'inviter_id' => $inviter->getKey(),
                'status' => CircleInvitation::PENDING,
                'responded_at' => null,
            ],
        );
    }
}
