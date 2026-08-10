<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\GroupsByLocalDay;
use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Wins per day across the platform, split by pillar.
 *
 * The unscoped twin of the owner console's activity chart, and the shape the
 * same component reads — so the staff view of the three pillars is drawn with
 * the same curves and the same colours a member already knows.
 *
 * Counted once per person per pillar per day, matching what a win is worth
 * everywhere else in the app: a second sitting on a Tuesday is still the one
 * Tuesday somebody meditated. Counting rows instead would let one enthusiast
 * logging six walks read as a busy day for the whole platform.
 */
class WinMixController extends Controller
{
    use GroupsByLocalDay, ResolvesStatWindow;

    /**
     * The detail table behind each pillar.
     *
     * @var array<string, string>
     */
    private const WIN_TABLES = [
        'meditation' => 'win_meditation',
        'learning' => 'win_learning',
        'movement' => 'win_movement',
    ];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $days = $this->windowDays($request);
        $windowStart = $this->windowStart($request);
        $windowEnd = $this->windowEnd($request);

        $counts = [];

        foreach (self::WIN_TABLES as $pillar => $table) {
            $counts[$pillar] = $this->dailyCounts($table, $windowStart, $windowEnd);
        }

        return response()->json([
            'days' => $days,
            'from' => Day::dateOf($windowStart),
            'to' => Day::dateOf($windowEnd),
            'series' => array_keys(self::WIN_TABLES),
            'points' => $this->fillCalendar($counts, $windowStart, $days),
        ]);
    }

    /**
     * Distinct people per day for one pillar.
     *
     * Bucketed on the display clock — see {@see GroupsByLocalDay}. Counting
     * the distinct people has to happen on the same side as the grouping, so
     * both moved out of the query together.
     *
     * @return array<string, int> Keyed by date.
     */
    private function dailyCounts(string $table, CarbonInterface $from, CarbonInterface $to): array
    {
        $posts = 'posts';

        return $this->distinctPerLocalDay(
            DB::table($table)
                ->join($posts, "{$posts}.id", '=', "{$table}.post_id")
                ->whereBetween("{$table}.completed_at", [$from, $to])
                ->select(
                    DB::raw("{$table}.completed_at AS at"),
                    DB::raw("{$posts}.user_id AS who"),
                )
        );
    }

    /**
     * Every day in the window, including the ones nothing happened on.
     *
     * A line drawn straight between two populated days would imply activity on
     * the quiet ones between them.
     *
     * @param  array<string, array<string, int>>  $counts
     * @return list<array<string, mixed>>
     */
    private function fillCalendar(array $counts, CarbonInterface $from, int $days): array
    {
        $points = [];
        $dates = $this->localDaysFrom($from, $days);

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $dates[$offset];

            $point = ['date' => $date];

            foreach (array_keys(self::WIN_TABLES) as $pillar) {
                $point[$pillar] = $counts[$pillar][$date] ?? 0;
            }

            $points[] = $point;
        }

        return $points;
    }
}
