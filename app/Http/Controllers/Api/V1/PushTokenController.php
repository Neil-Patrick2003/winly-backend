<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushTokenController extends Controller
{
    /**
     * Register this device for push.
     *
     * The token is the key, not the user: the same install signing in as
     * somebody else must move with them rather than leaving the previous
     * account receiving their notifications. `failed_at` is cleared because a
     * token being offered again is proof it works.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
        ]);

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->getKey(),
                'platform' => $validated['platform'] ?? null,
                'failed_at' => null,
            ],
        );

        return response()->json(['data' => ['registered' => true]], 201);
    }

    /**
     * Stop sending to this device — what signing out does.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:255']]);

        PushToken::query()
            ->where('token', $validated['token'])
            ->where('user_id', $request->user()->getKey())
            ->delete();

        return response()->json(['data' => ['registered' => false]]);
    }
}
