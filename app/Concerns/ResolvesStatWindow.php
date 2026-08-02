<?php

namespace App\Concerns;

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
            return today()->subDays($this->windowDays($request) - 1);
        }

        return $request->date('from')->startOfDay();
    }

    /**
     * The last day counted, taken to the end of it so a win logged at teatime
     * on the closing day is not left out of its own range.
     */
    protected function windowEnd(Request $request): CarbonInterface
    {
        return $request->filled('to')
            ? $request->date('to')->endOfDay()
            : today()->endOfDay();
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

        $days = (int) $request->date('from')->startOfDay()
            ->diffInDays($this->windowEnd($request)->startOfDay()) + 1;

        return max(1, min($days, self::MAX_WINDOW_DAYS));
    }

    /**
     * Where the preceding window of equal length opens.
     */
    protected function previousWindowStart(Request $request): CarbonInterface
    {
        return $this->windowStart($request)->subDays($this->windowDays($request));
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
