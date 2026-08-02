<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How many people sit in this owner's circles.
 *
 * The headline counts seats: one person in three of the owner's circles is
 * three seats filled, which is what a circle list adds up to. `people` reports
 * the distinct head count beside it, since those two diverge quickly.
 */
class MembersStatController extends Controller
{
    use ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $owner = $request->user();
        $windowStart = $this->windowStart($request);

        $row = $this->membershipsInOwnedCircles($owner)
            ->whereBetween('joined_at', [$this->previousWindowStart($request), $this->windowEnd($request)])
            ->selectRaw('SUM(CASE WHEN joined_at >= ? THEN 1 ELSE 0 END) AS current_count', [$windowStart])
            ->selectRaw('SUM(CASE WHEN joined_at < ? THEN 1 ELSE 0 END) AS previous_count', [$windowStart])
            ->first();

        $joined = (int) ($row->current_count ?? 0);
        $previous = (int) ($row->previous_count ?? 0);

        return response()->json([
            'value' => $this->membershipsInOwnedCircles($owner)->count(),
            'people' => $this->membershipsInOwnedCircles($owner)->distinct()->count('user_id'),
            'joined' => $joined,
            'previous' => $previous,
            'change' => $this->percentageChange($joined, $previous),
            'days' => $this->windowDays($request),
        ]);
    }
}
