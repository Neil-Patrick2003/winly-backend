<?php

namespace App\Concerns;

use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * The window a console statistic is measured over.
 *
 * Whole days, both ends included, matching the circle tracker: a win logged
 * this morning and one logged last night both belong to the day they happened
 * on, which is how a streak counts them too.
 *
 * Every tile compares its window against the stretch of equal length directly
 * before it — otherwise the change percentage weighs a long period against a
 * short one and always reads as growth.
 *
 * The bounds are local day boundaries handed back as the UTC instants they
 * fall on. Both halves matter: cut in UTC, a day here would run from eight in
 * the morning to eight the next and disagree with the streak and the week the
 * app already shows — and returned as local-clock values, every `whereBetween`
 * built on them would be compared against a UTC column and land off by the
 * offset. Use {@see Day::dateOf()} to name one of these days, never
 * `toDateString()`, which would read the UTC date the instant sits on.
 */
trait ResolvesStatWindow
{
    /**
     * How far back a statistic looks when the caller does not say.
     */
    protected const DEFAULT_DAYS = 7;

    /**
     * The longest range a caller may ask for, in days.
     */
    protected const MAX_WINDOW_DAYS = 366;

    /**
     * The first day counted.
     */
    protected function windowStart(Request $request): CarbonInterface
    {
        if (! $request->filled('from')) {
            return Day::utc(Day::startOf()->subDays($this->windowDays($request) - 1));
        }

        return Day::utc(Day::startOfDate($request->date('from')));
    }

    /**
     * The last day counted, taken to the end of it so a win logged at teatime
     * on the closing day is not left out of its own range.
     */
    protected function windowEnd(Request $request): CarbonInterface
    {
        return Day::utc(
            $request->filled('to')
                ? Day::startOfDate($request->date('to'))->endOfDay()
                : Day::startOf()->endOfDay()
        );
    }

    /**
     * How many days the range covers, both ends included.
     */
    protected function windowDays(Request $request): int
    {
        if (! $request->filled('from')) {
            return max(1, min(
                $request->integer('days', self::DEFAULT_DAYS),
                self::MAX_WINDOW_DAYS,
            ));
        }

        // Counted on local calendar days, not on the instants — an offset
        // that is not a whole number of days would otherwise lose one.
        $days = (int) Day::startOfDate($request->date('from'))
            ->diffInDays(Day::startOf($this->windowEnd($request))) + 1;

        return max(1, min($days, self::MAX_WINDOW_DAYS));
    }

    /**
     * Where the preceding window of equal length opens.
     */
    protected function previousWindowStart(Request $request): CarbonInterface
    {
        // Stepped back on the local clock and converted after, so a window
        // spanning a daylight-saving change is still the same run of days.
        return Day::utc(
            Day::startOf($this->windowStart($request))->subDays($this->windowDays($request))
        );
    }

    /**
     * How far the current count moved against the one before it, as a percentage.
     *
     * Null rather than zero when there is nothing to compare against: a jump
     * from no data is not growth, and rendering it as `+100%` would invent a
     * trend the numbers do not support.
     */
    protected function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
