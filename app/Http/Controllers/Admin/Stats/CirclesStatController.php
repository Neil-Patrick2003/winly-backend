<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * How many circles exist, and how many of them are in trouble.
 *
 * "Ownerless" is the number worth watching: nobody inside such a circle can
 * rename it, invite to it, or hand it on, because changing the owner needs the
 * owner's permission. Staff assigning one is the only way out, which is why it
 * sits on this page rather than in a report nobody opens.
 *
 * "Quiet" uses the same seven days the circle list's own filter does, so the
 * figure here and the tab there can never disagree.
 */
class CirclesStatController extends Controller
{
    /**
     * How recently a circle must have been posted in to count as active.
     */
    protected const ACTIVE_WITHIN_DAYS = 7;

    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(self::ACTIVE_WITHIN_DAYS);

        $recently = fn (Builder $posts) => $posts->where('posts.created_at', '>=', $since);

        /*
         * Three buckets that do not overlap and add up to the total, because
         * the pie draws them as parts of one whole. Ownerless comes out first
         * and the rest is split by activity — an ownerless circle counted again
         * under "quiet" would make the slices sum past 100% and the chart a
         * lie.
         */
        $ownerless = Circle::query()->whereNull('owner_id')->count();

        $ownedActive = Circle::query()
            ->whereNotNull('owner_id')
            ->whereHas('posts', $recently)
            ->count();

        $ownedQuiet = Circle::query()
            ->whereNotNull('owner_id')
            ->whereDoesntHave('posts', $recently)
            ->count();

        return response()->json([
            'value' => $ownerless + $ownedActive + $ownedQuiet,
            'ownerless' => $ownerless,
            'active' => $ownedActive,
            'quiet' => $ownedQuiet,
            'change' => null,
        ]);
    }
}
