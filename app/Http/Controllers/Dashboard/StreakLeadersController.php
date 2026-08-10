<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Day;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who in this owner's circles is on the longest run.
 *
 * The at-risk count is the actionable half: a streak whose owner has logged
 * nothing today is one quiet evening from resetting to zero.
 */
class StreakLeadersController extends Controller
{
    use ScopesToOwnedCircles;

    /**
     * How many people the board lists.
     */
    private const LIMIT = 4;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // The day on the display clock. `last_win_on` is written on it, so a
        // UTC date here marks the wrong people as having logged today.
        $today = Day::now()->toDateString();

        $leaders = $this->membersOnAStreak($request->user())
            ->orderByDesc('streak_days')
            ->orderBy('full_name')
            ->limit(self::LIMIT)
            // The photo is no longer a column to select; it comes with the row
            // through the media the model loads alongside itself.
            ->get(['id', 'full_name', 'username', 'streak_days', 'longest_streak', 'last_win_on']);

        $totals = $this->membersOnAStreak($request->user())
            ->select([
                DB::raw('COUNT(*) AS alive_count'),
                DB::raw('SUM(CASE WHEN last_win_on IS NULL OR last_win_on < ? THEN 1 ELSE 0 END) AS at_risk_count'),
            ])
            ->addBinding([$today], 'select')
            ->first();

        return response()->json([
            'alive' => (int) ($totals->alive_count ?? 0),
            'at_risk' => (int) ($totals->at_risk_count ?? 0),
            'data' => $leaders->map(fn (User $user): array => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'streak_days' => $user->streak_days,
                'longest_streak' => $user->longest_streak,
                'logged_today' => $user->last_win_on?->toDateString() === $today,
            ])->all(),
        ]);
    }

    /**
     * Members of the owner's circles who have a run going.
     *
     * @return Builder<User>
     */
    private function membersOnAStreak(User $owner): Builder
    {
        return User::query()
            ->where('streak_days', '>', 0)
            ->whereIn('id', $this->membershipsInOwnedCircles($owner)->select('user_id'));
    }
}
