<?php

namespace App\Http\Controllers\Admin\Stats;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * How many accounts exist, and how many of them are stuck outside.
 *
 * A running total rather than a windowed one, so it ignores the date picker:
 * "how big is this thing" is not a question about a fortnight.
 *
 * The unverified count rides along because it is the actionable half. Every
 * signed-in route sits behind the `verified` middleware, so somebody whose
 * confirmation mail never arrived has an account they cannot use and no way of
 * knowing why.
 */
class AccountsStatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $total = User::query()->count();
        $unverified = User::query()->whereNull('email_verified_at')->count();

        return response()->json([
            'value' => $total,
            'unverified' => $unverified,
            'closed' => User::onlyTrashed()->count(),
            'admins' => User::query()->where('is_admin', true)->count(),
            'change' => null,
        ]);
    }
}
