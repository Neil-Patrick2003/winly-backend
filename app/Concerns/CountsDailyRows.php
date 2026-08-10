<?php

namespace App\Concerns;

use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A day-by-day count for one table, with the quiet days filled in.
 *
 * Shared by the single-series charts so each of them stays a query and a title.
 * The calendar fill matters as much as the count: a line drawn straight between
 * two populated days would imply activity on the ones between them.
 */
trait CountsDailyRows
{
    use GroupsByLocalDay;

    /**
     * Rows created per day, as chart points.
     *
     * Bucketed on the display clock — see {@see GroupsByLocalDay}. The range
     * is still cut in the database, on the UTC instants the window resolves
     * to; only the grouping happens here.
     *
     * @return list<array{date: string, value: int}>
     */
    protected function dailySeries(
        string $table,
        CarbonInterface $from,
        int $days,
        string $column = 'created_at',
    ): array {
        $counts = $this->countPerLocalDay(
            DB::table($table)
                ->whereBetween($column, [$from, Day::utc(Day::startOf($from)->addDays($days))])
                ->select(DB::raw("{$column} AS at"))
        );

        return array_map(
            fn (string $date): array => ['date' => $date, 'value' => $counts[$date] ?? 0],
            $this->localDaysFrom($from, $days),
        );
    }
}
