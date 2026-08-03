<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posts shared into this owner's circles per day, averaged across the window.
 *
 * An average rather than today's tally: a single day swings on the hour it is
 * read at, and a KPI that reads differently before and after lunch is not one.
 */
class DailyPostsStatController extends Controller
{
    use ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = $this->windowDays($request);
        $windowStart = $this->windowStart($request);

        $row = $this->postsInOwnedCircles($request->user())
            ->whereBetween('created_at', [$this->previousWindowStart($request), $this->windowEnd($request)])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS current_count', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS previous_count', [$windowStart])
            ->first();

        $current = (int) ($row->current_count ?? 0);
        $previous = (int) ($row->previous_count ?? 0);

        return response()->json([
            'value' => round($current / $days, 1),
            'total' => $current,
            'previous' => round($previous / $days, 1),
            'change' => $this->percentageChange($current, $previous),
            'days' => $days,
        ]);
    }
}
