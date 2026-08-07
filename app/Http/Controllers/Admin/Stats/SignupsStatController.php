<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accounts opened in the window, against the stretch before it.
 *
 * A total rather than a per-day average, unlike posts: signups are lumpy by
 * nature — a mention somewhere puts fifty on one afternoon — and dividing that
 * by seven describes a week nobody had.
 *
 * Counts accounts that have since been closed. Somebody who signed up and left
 * still signed up, and quietly dropping them would make a bad week look like it
 * never happened.
 */
class SignupsStatController extends Controller
{
    use ResolvesStatWindow;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $windowStart = $this->windowStart($request);

        $row = User::withTrashed()
            ->whereBetween('created_at', [$this->previousWindowStart($request), $this->windowEnd($request)])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS current_count', [$windowStart])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS previous_count', [$windowStart])
            ->first();

        $current = (int) ($row->current_count ?? 0);
        $previous = (int) ($row->previous_count ?? 0);

        return response()->json([
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentageChange($current, $previous),
            'days' => $this->windowDays($request),
        ]);
    }
}
