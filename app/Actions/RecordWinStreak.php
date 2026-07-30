<?php

namespace App\Actions;

use App\Models\User;
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
        $today = ($on ?? Carbon::now())->startOfDay();
        $lastWin = $user->last_win_on?->startOfDay();

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
