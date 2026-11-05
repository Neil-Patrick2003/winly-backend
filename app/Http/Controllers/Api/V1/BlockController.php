<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BlockUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Blocking people across the whole app.
 *
 * A block hides the two people from each other everywhere: posts, comments,
 * stories, likes, follow lists, search and notifications. The other person is
 * not told.
 */
class BlockController extends Controller
{
    /**
     * The people the signed-in user has blocked, most recent first.
     *
     * Not paginated: it is a list people keep short, read only from Settings.
     *
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $blocked = $request->user()->blockedUsers()
            ->orderByPivot('created_at', 'desc')
            ->limit(500)
            ->get();

        return UserSummaryResource::collection($blocked);
    }

    public function store(Request $request, User $user, BlockUser $block): JsonResponse
    {
        $blocker = $request->user();

        if ($blocker->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot block yourself.']);
        }

        $block->execute($blocker, $user);

        return response()->json(['data' => ['user_id' => $user->id, 'is_blocked' => true]]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $request->user()->blockedUsers()->detach($user->getKey());

        return response()->json(['data' => ['user_id' => $user->id, 'is_blocked' => false]]);
    }
}
