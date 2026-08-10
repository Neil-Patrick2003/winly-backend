<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\CountsDailyRows;
use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Support\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accounts opened per day.
 *
 * Its own endpoint rather than sharing one with posts: the two are drawn as
 * separate plots — signups arrive a handful a day where posts arrive in dozens,
 * and one set of axes for both would need a second scale — so pairing them in a
 * single response only meant one slow query could hold up the other's chart.
 *
 * Closed accounts included. Somebody who signed up and later left still signed
 * up, and dropping them rewrites the day they arrived on.
 */
class SignupsSeriesController extends Controller
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
            // `$from` is the UTC instant local midnight falls on, so the
            // date has to be read back on the display clock.
            'from' => Day::dateOf($from),
            'points' => $this->dailySeries('users', $from, $days),
        ]);
    }
}
