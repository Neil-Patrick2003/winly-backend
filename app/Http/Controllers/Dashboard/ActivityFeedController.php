<?php

namespace App\Http\Controllers\Dashboard;

use App\Concerns\ScopesToOwnedCircles;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The most recent wins shared into this owner's circles.
 */
class ActivityFeedController extends Controller
{
    use ScopesToOwnedCircles;

    /**
     * How many entries the feed shows.
     */
    private const LIMIT = 2;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $posts = $this->postsInOwnedCircles($request->user())
            ->with([
                'user:id,full_name,username,avatar_url',
                'winMeditation:id,post_id,duration_minutes',
                'winLearning:id,post_id,learned_text',
                'winMovement:id,post_id,movement_type',
            ])
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'total' => $this->postsInOwnedCircles($request->user())->count(),
            'data' => $posts->map(fn (Post $post): array => [
                'id' => $post->id,
                'caption' => $post->caption,
                'created_at' => $post->created_at?->toIso8601String(),
                'user' => [
                    'id' => $post->user->id,
                    'full_name' => $post->user->full_name,
                    'username' => $post->user->username,
                    'avatar_url' => $post->user->avatar_url,
                ],
                'wins' => $this->winsFor($post),
            ])->all(),
        ]);
    }

    /**
     * The kinds of win a post carries, named for display.
     *
     * Read from the three eager-loaded relations rather than queried per post,
     * so a feed of six costs four queries rather than nineteen.
     *
     * @return list<string>
     */
    private function winsFor(Post $post): array
    {
        return array_keys(array_filter([
            'meditation' => $post->winMeditation,
            'learning' => $post->winLearning,
            'movement' => $post->winMovement,
        ]));
    }
}
