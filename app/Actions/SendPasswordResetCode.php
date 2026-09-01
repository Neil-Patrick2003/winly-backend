<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\ResetPasswordCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Issue a one-time code that lets someone set a new password from the app.
 *
 * The code is kept in `password_reset_tokens`, the same table the web link
 * flow uses, and under the same rule: one live credential per address. Asking
 * for a code therefore retires an outstanding web link and vice versa, which
 * is the point — a password reset that is halfway through on two channels at
 * once is a way in, not a convenience.
 *
 * Only the hash is stored. A leaked database read yields nothing that can be
 * typed into the app, which matters more here than for Laravel's own random
 * tokens: six digits are guessable if you can see them being compared.
 *
 * The mail goes out inside this call rather than on a queue — see
 * `ResetPasswordCode` for why. This action owns the row from end to end: it
 * writes it, sends, and takes it back out through `discard()` if the send does
 * not get through.
 */
class SendPasswordResetCode
{
    /**
     * How long a code stays good for.
     *
     * Far shorter than the hour the web link gets. The whole search space is a
     * million codes, so the window is the main thing keeping a patient guesser
     * out — the per-address attempt limit on the reset endpoint does the rest.
     */
    public const EXPIRES_MINUTES = 15;

    /**
     * How long before another code can be asked for.
     *
     * Matches the sending throttle Laravel's own password broker keeps, and is
     * what stops the endpoint being used to post mail to somebody repeatedly.
     */
    public const THROTTLE_SECONDS = 60;

    /**
     * Generate, store and email a fresh code.
     *
     * Silently does nothing when one was sent moments ago, rather than failing:
     * the caller answers the same way whether or not a code went out, so that
     * the endpoint cannot be used to find out which addresses have accounts.
     */
    public function handle(User $user): void
    {
        if ($this->throttled($user)) {
            return;
        }

        $code = $this->generate();

        DB::table($this->table())->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => Carbon::now()],
        );

        try {
            $user->notify(new ResetPasswordCode($code));
        } catch (Throwable $failure) {
            /*
             * The row has to be written before the send — a code that reached
             * somebody's inbox without being stored is a code that does not
             * work — but leaving it there after a failed send is worse than
             * useless. It marks the address as having been sent to moments
             * ago, so the retry a minute later is silently skipped, and the
             * person waits for a second mail that was never going to come.
             *
             * Undoing it costs nothing: nothing was delivered, so there is no
             * code out there for this row to be the record of.
             *
             * Everything that can stop the mail arrives here now that the send
             * is not deferred: a refused sender, a rejected API key, Brevo
             * being unreachable. The exception carries on up — the caller is
             * the one that decides what to say about it.
             */
            $this->discard($user->email, $code);

            throw $failure;
        }
    }

    /**
     * Drop the stored row for a code that never reached anybody.
     *
     * Matched on the hash rather than the address alone. Usually the send fails
     * within moments of the row being written and the two are plainly the same
     * code — but a send that hangs until it times out can outlast the throttle
     * window, and a second request in the meantime will have written a fresh,
     * working code over the top. Clearing by address alone would take that one
     * away from somebody already typing it in. Only the row this code wrote is
     * its to remove.
     */
    public function discard(string $email, string $code): void
    {
        $record = DB::table($this->table())->where('email', $email)->first();

        if ($record === null || ! Hash::check($code, $record->token)) {
            return;
        }

        DB::table($this->table())->where('email', $email)->delete();
    }

    /**
     * Whether a code was sent to this address too recently to send another.
     */
    public function throttled(User $user): bool
    {
        $sentAt = DB::table($this->table())
            ->where('email', $user->email)
            ->value('created_at');

        return $sentAt !== null
            && Carbon::parse($sentAt)->addSeconds(self::THROTTLE_SECONDS)->isFuture();
    }

    /**
     * A six-digit code, zero-padded so every one of them is six characters.
     *
     * `random_int` rather than `rand`: this is a credential, and the difference
     * is whether the sequence can be predicted from earlier codes.
     */
    private function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * The table the configured password broker keeps its tokens in.
     */
    private function table(): string
    {
        return config('auth.passwords.'.config('auth.defaults.passwords').'.table');
    }
}
