<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The share of posts in this owner's circles that drew a like or a comment.
 *
 * A true rate, bounded at 100, rather than interactions per post: this app has
 * no impression count to divide by, and "how many posts landed at all" is the
 * question a quiet circle actually raises.
 *
 * Read from the denormalised counters on posts, so no join is needed.
 */
class EngagementStatController extends Controller
{
    use ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $windowStart = $this->windowStart($request);

        $row = $this->postsInOwnedCircles($request->user())
            ->whereBetween('created_at', [$this->previousWindowStart($request), $this->windowEnd($request)])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS current_total', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS previous_total', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND (likes_count > 0 OR comments_count > 0) THEN 1 ELSE 0 END) AS current_engaged', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at < ? AND (likes_count > 0 OR comments_count > 0) THEN 1 ELSE 0 END) AS previous_engaged', [$windowStart])
            ->first();

        $current = $this->rate((int) ($row->current_engaged ?? 0), (int) ($row->current_total ?? 0));
        $previous = $this->rate((int) ($row->previous_engaged ?? 0), (int) ($row->previous_total ?? 0));

        return response()->json([
            'value' => $current,
            'previous' => $previous,
            'engaged' => (int) ($row->current_engaged ?? 0),
            'total' => (int) ($row->current_total ?? 0),
            'change' => $previous > 0 ? round($current - $previous, 1) : null,
            'days' => $this->windowDays($request),
        ]);
    }

    /**
     * What share of the posts drew something, as a percentage.
     *
     * Zero posts is zero rather than null: an empty window has no engagement,
     * and the tile needs a number to sit under the heading.
     */
    private function rate(int $engaged, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($engaged / $total) * 100, 1);
    }
}
