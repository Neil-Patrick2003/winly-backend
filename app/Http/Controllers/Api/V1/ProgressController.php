<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProgressController extends Controller
{
    /**
     * The week so far, a day at a time.
     *
     * Seven entries, Monday to Sunday, each saying which of the three kinds of
     * win were logged that day. That shape is the point: the client draws a
     * ring per day split into three, so it needs a straight yes or no per kind
     * per day rather than counts it would have to reduce itself.
     *
     * Days are judged by the win's own `completed_at`, not by when the post was
     * written — someone logging Monday's walk on Tuesday morning did the walk
     * on Monday, and the week should say so.
     *
     * The streak rides along because the home screen shows it beside this, and
     * two endpoints answering the same question is how they come to disagree.
     */
    public function week(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * Monday to Sunday of the week containing today, on the display clock
         * — the same one `currentStreak` is judged on, so the badge and the
         * row underneath it never disagree about where a day ends.
         *
         * Not UTC. At UTC+8 the UTC date turns over at eight in the morning,
         * so every morning before then this marked yesterday as today and the
         * whole strip read eight hours stale.
         */
        $start = Day::now()->startOfWeek();
        $end = Day::now()->endOfWeek();
        $today = Day::startOf();

        $meditation = $this->daysLogged(WinMeditation::query()->getModel(), $user, $start, $end);
        $learning = $this->daysLogged(WinLearning::query()->getModel(), $user, $start, $end);
        $movement = $this->daysLogged(WinMovement::query()->getModel(), $user, $start, $end);

        $days = collect(range(0, 6))->map(function (int $offset) use (
            $start,
            $today,
            $meditation,
            $learning,
            $movement,
        ): array {
            $day = $start->copy()->addDays($offset);
            $date = $day->toDateString();

            return [
                'date' => $date,
                // Sent rather than derived, so every client shows the same
                // three letters instead of each inventing its own shortening.
                'weekday' => $day->format('D'),
                'day_of_month' => $day->day,
                'is_today' => $day->isSameDay($today),
                /*
                 * A day that has not happened yet, which the client draws
                 * differently: an empty ring on Friday is a day still to come,
                 * and the same ring on Monday is a day that went by.
                 */
                'is_future' => $day->greaterThan($today),
                'meditation' => $meditation->contains($date),
                'learning' => $learning->contains($date),
                'movement' => $movement->contains($date),
            ];
        });

        return response()->json([
            'data' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'streak_days' => $user->currentStreak(),
                'longest_streak' => $user->longest_streak,
                'days' => $days,
            ],
        ]);
    }

    /**
     * The days in the window on which this user logged one kind of win.
     *
     * Returned as date strings rather than counts: the ring is drawn from
     * whether a kind happened at all, and two meditations on a Tuesday is
     * still one Tuesday.
     *
     * Grouped in PHP rather than by the database, which would mean a raw
     * `DATE()` that reads differently across engines. A week of one person's
     * wins is a handful of rows.
     *
     * @param  WinMeditation|WinLearning|WinMovement  $win  The table to look in.
     * @return Collection<int, string>
     */
    protected function daysLogged(
        WinMeditation|WinLearning|WinMovement $win,
        User $user,
        Carbon $start,
        Carbon $end,
    ): Collection {
        return $win->newQuery()
            // A subquery rather than `whereHas`, since the only thing being
            // asked of the post is who wrote it.
            ->whereIn('post_id', $user->posts()->select('posts.id'))
            // The bounds are local midnights and the column is UTC, so they
            // are converted before they go anywhere near the query — a local
            // bound compared against a UTC column is wrong by the offset, and
            // drops the first hours of Monday into the week before.
            ->whereBetween('completed_at', [Day::utc($start), Day::utc($end)])
            ->pluck('completed_at')
            // `CarbonInterface` rather than a concrete class: the application
            // dates are immutable, so a cast attribute arrives as
            // `CarbonImmutable` and not the `Carbon` the helpers here return.
            //
            // Read back on the display clock too: taking the date off the
            // stored UTC value answers the UTC question, which is how a win
            // logged at one in the morning ended up in the previous cell.
            ->map(fn (CarbonInterface $at): string => Day::dateOf($at))
            ->unique()
            ->values();
    }
}
