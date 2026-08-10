<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rebuild a user's win totals from the wins themselves.
 *
 * {@see RecordWinStreak} only ever moves forward: it credits today and carries
 * yesterday's run on. That is the right thing while wins only ever arrive, and
 * useless once one can be edited to a different day or deleted outright —
 * neither of which it can express. Deleting the only win of today has to be
 * able to take the streak back down, and nothing incremental can do that.
 *
 * So this reads the wins and states what they add up to, rather than trying to
 * adjust by a delta. It is the slower answer and the one that cannot drift.
 */
class RecalculateWinStats
{
    /**
     * Recount a user's wins and rebuild the streak columns around them.
     */
    public function execute(User $user): void
    {
        $days = collect();
        $total = 0;

        foreach ([new WinMeditation, new WinLearning, new WinMovement] as $win) {
            /*
             * A subquery rather than `whereHas`, matching how the weekly
             * progress asks the same question: the only thing wanted from the
             * post is who wrote it.
             */
            $logged = $win->newQuery()
                ->whereIn('post_id', $user->posts()->select('posts.id'))
                ->pluck('completed_at')
                // Grouped in PHP rather than by a raw `DATE()`, which reads
                // differently across database engines. Same reasoning as the
                // weekly progress endpoint, and the same answer as a result.
                //
                // On the display clock, so a win logged after local midnight
                // is the day it felt like rather than the UTC day it is still
                // inside — otherwise a recount disagrees with what the streak
                // and the week already said.
                ->map(fn (CarbonInterface $at): string => Day::dateOf($at))
                /*
                 * A pillar counts once a day. Sitting twice on a Tuesday is
                 * still the one Tuesday you meditated — the same Tuesday the
                 * streak counts once and the week draws as a single ring.
                 * Meditation, learning and movement are counted apart, so a
                 * day of all three is still worth three.
                 */
                ->unique();

            $total += $logged->count();

            $days = $days->concat($logged);
        }

        $days = $days->unique()->sort()->values();

        $user->wins_count = $total;
        $user->last_win_on = $days->isEmpty() ? null : Carbon::parse($days->last());
        $user->streak_days = $this->runEndingAtTheLast($days);

        /*
         * A high-water mark, not a recount.
         *
         * The other three columns describe what is true now, and correcting
         * them is the whole point. This one is a record of something that
         * happened, and tidying up an old post is no reason to take it away.
         * So it only ever rises: `max` of what was already stored and what the
         * remaining wins can still account for.
         */
        $user->longest_streak = max($user->longest_streak, $this->longestRun($days));

        $user->save();
    }

    /**
     * The length of the unbroken run of days ending on the most recent one.
     *
     * This is what `streak_days` holds — a run measured back from the last win
     * rather than from today, which is why a lapsed streak still has a number
     * in the column and `User::currentStreak()` is what decides it has ended.
     *
     * @param  Collection<int, string>  $days  Distinct dates, ascending.
     */
    protected function runEndingAtTheLast(Collection $days): int
    {
        if ($days->isEmpty()) {
            return 0;
        }

        $run = 1;
        $cursor = Carbon::parse($days->last());

        // Walk backwards from the most recent day, stopping at the first gap.
        for ($i = $days->count() - 2; $i >= 0; $i--) {
            $previous = Carbon::parse($days[$i]);

            if (! $previous->isSameDay($cursor->copy()->subDay())) {
                break;
            }

            $run++;
            $cursor = $previous;
        }

        return $run;
    }

    /**
     * The longest unbroken run anywhere in the history.
     *
     * @param  Collection<int, string>  $days  Distinct dates, ascending.
     */
    protected function longestRun(Collection $days): int
    {
        if ($days->isEmpty()) {
            return 0;
        }

        $longest = 1;
        $run = 1;

        for ($i = 1; $i < $days->count(); $i++) {
            $run = Carbon::parse($days[$i - 1])->copy()->addDay()->isSameDay(Carbon::parse($days[$i]))
                ? $run + 1
                : 1;

            $longest = max($longest, $run);
        }

        return $longest;
    }
}
