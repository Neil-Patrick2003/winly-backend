<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How many people have a streak still standing.
 *
 * The single best retention number this product has: a live streak is somebody
 * who showed up today or yesterday and intends to again. Everything else on
 * this page counts events; this counts habits.
 *
 * "Still standing" is the same rule {@see User::currentStreak()} applies — a
 * win today or yesterday — asked of the whole table at once rather than a row
 * at a time. The stored `streak_days` column is deliberately not consulted: it
 * holds the run ending at somebody's last win whenever that was, so counting
 * rows where it is above zero would count streaks that ended months ago.
 *
 * Ignores the window. A streak is a fact about now, and a date range cannot
 * make it one about last March.
 */
class LiveStreaksStatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Local, not UTC: `last_win_on` records the day on the display clock,
        // so asking with a UTC `today()` counts a different set for eight
        // hours either side of midnight than the app itself would.
        $yesterday = Day::startOf()->subDay();

        $live = User::query()
            ->whereNotNull('last_win_on')
            ->whereDate('last_win_on', '>=', $yesterday)
            ->count();

        $total = User::query()->count();

        return response()->json([
            'value' => $live,
            'total' => $total,
            'share' => $total === 0 ? null : round(($live / $total) * 100, 1),
            'longest' => (int) User::query()->max('longest_streak'),
            'change' => null,
        ]);
    }
}
