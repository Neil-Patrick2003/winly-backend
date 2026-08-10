<?php

namespace App\Actions;

use App\Models\User;
use App\Support\Day;
use Illuminate\Support\Carbon;

class RecordWinStreak
{
    /**
     * Credit a user with a win for the day it landed on.
     *
     * A streak counts days, not posts. The second and third win of the same
     * day leave it exactly where the first one put it; a win the following day
     * carries it forward; a missed day starts it over at one.
     *
     * @param  Carbon|null  $on  The day the win landed, defaulting to today.
     */
    public function execute(User $user, ?Carbon $on = null): void
    {
        // The day this landed on, read on the display clock. In UTC a win
        // logged before eight in the morning is filed under yesterday, which
        // both credits the wrong day and lets the next one look like a repeat
        // of it rather than the day that carries the streak forward.
        $today = Day::startOf($on);
        // Rebuilt on the same clock as `$today`. Read straight off the model
        // it carries the application timezone, and a UTC midnight is never
        // equal to a local one — every day would look like a fresh start.
        $lastWin = $user->last_win_on === null ? null : Day::startOfDate($user->last_win_on);

        if ($lastWin?->equalTo($today)) {
            return;
        }

        $user->streak_days = $lastWin?->equalTo($today->copy()->subDay())
            ? $user->streak_days + 1
            : 1;

        $user->longest_streak = max($user->longest_streak, $user->streak_days);
        $user->last_win_on = $today;

        $user->save();
    }
}
