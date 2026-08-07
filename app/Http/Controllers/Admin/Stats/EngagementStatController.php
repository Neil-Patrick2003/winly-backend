<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The share of posts that drew a like or a comment, platform-wide.
 *
 * A true rate bounded at 100 rather than interactions per post: the app has no
 * impression count to divide by, and "how many wins landed at all" is the
 * question a quiet platform actually raises. The unscoped twin of the owner
 * console's tile, computed the same way so the two never disagree about a
 * circle they both cover.
 *
 * Read off the denormalised counters on posts, so no join is needed.
 */
class EngagementStatController extends Controller
{
    use ResolvesStatWindow;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $windowStart = $this->windowStart($request);

        $row = Post::query()
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
