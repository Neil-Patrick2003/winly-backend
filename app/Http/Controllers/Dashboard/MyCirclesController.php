<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The circles this person owns, with how many are in each.
 */
class MyCirclesController extends Controller
{
    use ScopesToOwnedCircles;

    /**
     * How many circles the panel lists.
     */
    private const LIMIT = 2;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $circles = $this->ownedCircles($request->user())
            ->orderByDesc('members_count')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'icon_initial', 'color_hex', 'tag', 'members_count']);

        return response()->json([
            'total' => $this->ownedCircles($request->user())->count(),
            'data' => $circles->map(fn (Circle $circle): array => [
                'id' => $circle->id,
                'name' => $circle->name,
                'icon_initial' => $circle->icon_initial,
                'color_hex' => $circle->color_hex,
                'tag' => $circle->tag,
                'members_count' => $circle->members_count,
            ])->all(),
        ]);
    }
}
