<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Wins logged in the window, counted the way the app counts them.
 *
 * One per person per pillar per day — the same rule the streak, the weekly ring
 * and everybody's total already apply. Counting detail rows instead would let
 * one enthusiast logging six walks read as a busy day for the whole platform,
 * and would disagree with every wins figure a member can see.
 *
 * Not the same as posts: one post can carry all three pillars, and a post with
 * no wins on it cannot exist.
 */
class WinsLoggedStatController extends Controller
{
    use ResolvesStatWindow;

    /**
     * The detail table behind each pillar.
     *
     * @var list<string>
     */
    private const WIN_TABLES = ['win_meditation', 'win_learning', 'win_movement'];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $windowStart = $this->windowStart($request);

        $current = $this->countBetween($windowStart, $this->windowEnd($request));
        $previous = $this->countBetween($this->previousWindowStart($request), $windowStart);

        return response()->json([
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentageChange($current, $previous),
            'days' => $this->windowDays($request),
        ]);
    }

    /**
     * Distinct person-days per pillar, summed across the three tables.
     *
     * Grouped in a subquery and the groups counted, rather than counting
     * distinct over a concatenated key: string concatenation is `CONCAT` on
     * MySQL and `||` on SQLite, and the suite runs on the one the app does not.
     */
    private function countBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $total = 0;

        foreach (self::WIN_TABLES as $table) {
            $daily = DB::table($table)
                ->join('posts', 'posts.id', '=', "{$table}.post_id")
                ->whereBetween("{$table}.completed_at", [$from, $to])
                ->groupBy('posts.user_id', 'day')
                ->select('posts.user_id', DB::raw("DATE({$table}.completed_at) AS day"));

            $total += DB::query()->fromSub($daily, 'daily')->count();
        }

        return $total;
    }
}
