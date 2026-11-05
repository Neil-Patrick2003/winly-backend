<?php

namespace App\Actions\Moderation;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account off, or back on.
 *
 * Off means: signed out everywhere at once, unable to sign back in, and every
 * post, comment, story and the profile itself hidden from other people. It is
 * the reversible alternative to deleting somebody: nothing they made is lost.
 */
class DisableAccount
{
    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['disabled_at' => now()])->save();

            $user->tokens()->delete();
            $user->pushTokens()->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        });
    }

    public function enable(User $user): void
    {
        $user->forceFill(['disabled_at' => null])->save();
    }
}
