<?php

namespace App\Concerns;

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
    /**
     * Rows created per day, as chart points.
     *
     * `DATE()` rather than a driver-specific expression: the app runs on MySQL
     * and the suite on SQLite, and both read it the same way.
     *
     * @return list<array{date: string, value: int}>
     */
    protected function dailySeries(
        string $table,
        CarbonInterface $from,
        int $days,
        string $column = 'created_at',
    ): array {
        $counts = DB::table($table)
            ->whereBetween($column, [$from, $from->copy()->addDays($days)->startOfDay()])
            ->groupBy('day')
            ->select(DB::raw("DATE({$column}) AS day"), DB::raw('COUNT(*) AS total'))
            ->pluck('total', 'day')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $points = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset)->toDateString();

            $points[] = ['date' => $date, 'value' => $counts[$date] ?? 0];
        }

        return $points;
    }
}
