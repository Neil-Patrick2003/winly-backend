<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Spend a reset code: check it, and on success make sure it cannot be spent
 * again.
 *
 * Verifying and deleting are one operation on purpose. A code that survived
 * being accepted is a code that resets the password twice, and the second time
 * is by whoever else has read the email since.
 */
class ConsumePasswordResetCode
{
    /**
     * Whether the code is the live one for this user, deleting it if so.
     */
    public function handle(User $user, string $code): bool
    {
        $record = DB::table($this->table())
            ->where('email', $user->email)
            ->first();

        if ($record === null) {
            return false;
        }

        // Expiry is checked before the hash rather than after, so a stale code
        // is turned away without spending time on bcrypt.
        $expired = Carbon::parse($record->created_at)
            ->addMinutes(SendPasswordResetCode::EXPIRES_MINUTES)
            ->isPast();

        if ($expired) {
            DB::table($this->table())->where('email', $user->email)->delete();

            return false;
        }

        if (! Hash::check($code, $record->token)) {
            return false;
        }

        DB::table($this->table())->where('email', $user->email)->delete();

        return true;
    }

    /**
     * The table the configured password broker keeps its tokens in.
     */
    private function table(): string
    {
        return config('auth.passwords.'.config('auth.defaults.passwords').'.table');
    }
}
