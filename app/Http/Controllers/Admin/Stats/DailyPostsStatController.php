<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posts shared across the whole platform per day, averaged over the window.
 *
 * The unscoped twin of the owner console's tile, and an average for the same
 * reason: a single day's tally swings on the hour it is read at, and a figure
 * that reads differently before and after lunch is not one worth watching.
 */
class DailyPostsStatController extends Controller
{
    use ResolvesStatWindow;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = $this->windowDays($request);
        $windowStart = $this->windowStart($request);

        $row = Post::query()
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
