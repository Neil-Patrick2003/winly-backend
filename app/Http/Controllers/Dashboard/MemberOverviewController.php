<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\GroupsByLocalDay;
use App\Concerns\ResolvesStatWindow;
use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use App\Models\CircleBlock;
use App\Models\CircleInvitation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * How the owner's circles filled up, and where everyone around them stands.
 *
 * Two questions on one panel: the shape of the joining over time, and the
 * standing counts an owner can act on — invitations still waiting, and anyone
 * barred.
 */
class MemberOverviewController extends Controller
{
    use GroupsByLocalDay, ResolvesStatWindow, ScopesToOwnedCircles;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $owner = $request->user();
        $days = $this->windowDays($request);
        $windowStart = $this->windowStart($request);

        // Bucketed on the display clock — see `GroupsByLocalDay`. The range is
        // still cut in the database; only the grouping moved out of it.
        $joins = $this->countPerLocalDay(
            $this->membershipsInOwnedCircles($owner)
                ->whereBetween('joined_at', [$windowStart, $this->windowEnd($request)])
                ->select(DB::raw('joined_at AS at'))
        );

        return response()->json([
            'days' => $days,
            'points' => $this->fillCalendar($joins, $windowStart, $days),
            'statuses' => $this->statuses($owner),
        ]);
    }

    /**
     * Where the people around these circles currently stand.
     *
     * Accepted is read from the memberships rather than from invitations
     * carrying that status: whoever started a circle is in it without ever
     * having been invited, and joining without an invitation is a route too.
     *
     * @return array<string, int>
     */
    private function statuses(User $owner): array
    {
        $invitations = CircleInvitation::query()
            ->whereIn('circle_id', $this->ownedCircleIds($owner))
            ->select([
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_count'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS declined_count'),
            ])
            ->addBinding([CircleInvitation::PENDING, CircleInvitation::DECLINED], 'select')
            ->first();

        return [
            'accepted' => $this->membershipsInOwnedCircles($owner)->count(),
            'pending' => (int) ($invitations->pending_count ?? 0),
            'declined' => (int) ($invitations->declined_count ?? 0),
            'blocked' => CircleBlock::query()
                ->whereIn('circle_id', $this->ownedCircleIds($owner))
                ->count(),
        ];
    }

    /**
     * Every day in the window, including the ones nobody joined on.
     *
     * @param  array<string, int>  $joins
     * @return list<array{date: string, joined: int}>
     */
    private function fillCalendar(array $joins, CarbonInterface $from, int $days): array
    {
        return array_map(
            fn (string $date): array => ['date' => $date, 'joined' => $joins[$date] ?? 0],
            $this->localDaysFrom($from, $days),
        );
    }
}
