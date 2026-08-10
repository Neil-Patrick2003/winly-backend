<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\GroupsByLocalDay;
use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Day;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Wins per day in this owner's circles, split by kind, for the activity chart.
 */
class ActivityOverviewController extends Controller
{
    use GroupsByLocalDay, ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * The detail table behind each win type.
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
        $owner = $request->user();

        $counts = [];

        foreach (self::WIN_TABLES as $type => $table) {
            $counts[$type] = $this->dailyCounts($table, $owner, $windowStart, $windowEnd);
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
     * One grouped count per day for a single detail table.
     *
     * Bucketed on the display clock — see {@see GroupsByLocalDay}.
     *
     * @return array<string, int> Keyed by date.
     */
    private function dailyCounts(string $table, User $owner, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->countPerLocalDay(
            $this->winsInOwnedCircles($table, $owner)
                ->whereBetween('completed_at', [$from, $to])
                ->select(DB::raw('completed_at AS at'))
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

            foreach (array_keys(self::WIN_TABLES) as $type) {
                $point[$type] = $counts[$type][$date] ?? 0;
            }

            $points[] = $point;
        }

        return $points;
    }
}
