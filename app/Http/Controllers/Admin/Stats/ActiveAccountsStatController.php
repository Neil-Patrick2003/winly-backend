<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Concerns\ResolvesStatWindow;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How many accounts were seen in the window.
 *
 * Read off `last_active_at`, which holds only the most recent visit — so this
 * answers "how many people were about at some point in these days", not how
 * many visits there were. Somebody who opened the app every morning counts
 * once, which is the number worth watching.
 *
 * The previous window cannot be recovered from a single column: once somebody
 * returns, the timestamp of their earlier visit is gone. So no change figure is
 * offered rather than a wrong one — a comparison against a window that can only
 * ever look emptier the longer ago it was would read as growth every time.
 */
class ActiveAccountsStatController extends Controller
{
    use ResolvesStatWindow;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $active = User::query()
            ->whereBetween('last_active_at', [
                $this->windowStart($request),
                $this->windowEnd($request),
            ])
            ->count();

        $total = User::query()->count();

        return response()->json([
            'value' => $active,
            'total' => $total,
            'share' => $total === 0 ? null : round(($active / $total) * 100, 1),
            'change' => null,
            'days' => $this->windowDays($request),
        ]);
    }
}
