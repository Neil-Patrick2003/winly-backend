<?php

namespace App\Concerns;

use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Chart buckets cut where the days actually fall.
 *
 * `DATE(column)` groups on whatever clock the column is stored in, which is
 * UTC — so at UTC+8 every bucket ran from eight in the morning to eight the
 * next, and a chart drawn from them disagreed with the streak and the week
 * strip the app shows about which day anything happened on.
 *
 * Folded in PHP rather than shifted in SQL. Shifting means a driver-specific
 * interval expression — `DATE(col, '+8 hours')` on SQLite against
 * `DATE(col + INTERVAL 8 HOUR)` on MySQL, with the suite running on the engine
 * the app does not — and pinning a fixed offset would quietly go wrong in any
 * timezone that keeps daylight saving. The window is a bounded run of days, so
 * this reads one column over a range that was going to be scanned anyway.
 *
 * The same reasoning, and the same answer, as the weekly progress endpoint.
 */
trait GroupsByLocalDay
{
    /**
     * How many rows fall on each local day.
     *
     * The query must select its timestamp as `at`.
     *
     * @return array<string, int> Keyed by date.
     */
    protected function countPerLocalDay(Builder $query): array
    {
        return $query->pluck('at')
            ->map(fn ($at): string => Day::dateOf(Carbon::parse($at)))
            ->countBy()
            ->all();
    }

    /**
     * How many distinct people appear on each local day.
     *
     * The query must select its timestamp as `at` and the person as `who`.
     *
     * @return array<string, int> Keyed by date.
     */
    protected function distinctPerLocalDay(Builder $query): array
    {
        return $query->get()
            ->groupBy(fn (object $row): string => Day::dateOf(Carbon::parse($row->at)))
            ->map(fn ($rows): int => $rows->pluck('who')->unique()->count())
            ->all();
    }

    /**
     * How many distinct person-and-day pairs there are across the whole range.
     *
     * The query must select its timestamp as `at` and the person as `who`.
     */
    protected function countLocalPersonDays(Builder $query): int
    {
        return $query->get()
            ->map(fn (object $row): string => $row->who.'@'.Day::dateOf(Carbon::parse($row->at)))
            ->unique()
            ->count();
    }

    /**
     * Every local day in the window, in order, as `2026-08-10`.
     *
     * Walked on the local clock rather than by adding days to the stored
     * instant, so a run crossing a daylight-saving change still names one date
     * per day.
     *
     * @return list<string>
     */
    protected function localDaysFrom(CarbonInterface $from, int $days): array
    {
        $cursor = Day::startOf($from);

        return array_map(
            fn (int $offset): string => $cursor->copy()->addDays($offset)->toDateString(),
            range(0, $days - 1),
        );
    }
}
