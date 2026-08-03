<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How many circles this owner runs, and how many are new.
 *
 * The headline is the running total; the change compares circles started in
 * this window against the one before, since a total that only climbs cannot
 * move by a percentage that means anything.
 */
class CirclesStatController extends Controller
{
    use ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $owner = $request->user();
        $windowStart = $this->windowStart($request);

        $row = $this->ownedCircles($owner)
            ->whereBetween('created_at', [$this->previousWindowStart($request), $this->windowEnd($request)])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS current_count', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS previous_count', [$windowStart])
            ->first();

        $started = (int) ($row->current_count ?? 0);
        $previous = (int) ($row->previous_count ?? 0);

        return response()->json([
            'value' => $this->ownedCircles($owner)->count(),
            'started' => $started,
            'previous' => $previous,
            'change' => $this->percentageChange($started, $previous),
            'days' => $this->windowDays($request),
        ]);
    }
}
