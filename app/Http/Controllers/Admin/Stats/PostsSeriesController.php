<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\CountsDailyRows;
use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wins shared per day, across every circle.
 *
 * Drawn on its own axes rather than beside signups: see
 * {@see SignupsSeriesController} for why the two are never one plot.
 */
class PostsSeriesController extends Controller
{
    use CountsDailyRows, ResolvesStatWindow;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = $this->windowDays($request);
        $from = $this->windowStart($request);

        return response()->json([
            'days' => $days,
            'from' => $from->toDateString(),
            'points' => $this->dailySeries('posts', $from, $days),
        ]);
    }
}
