<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordNotification;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostLikeResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostLikeController extends Controller
{
    /**
     * Like a post.
     *
     * Liking twice is not an error, it just does not count twice, so a client
     * that double tapped or retried a dropped request lands in one place.
     */
    public function store(Request $request, Post $post, RecordNotification $notify): JsonResponse
    {
        // Nothing you cannot read is yours to react to — and a like notifies
        // the author, so without this a stranger could tap on a circle win and
        // announce themselves to somebody who never shared it with them.
        Gate::authorize('view', $post);

        $created = DB::transaction(function () use ($request, $post): bool {
            $like = $post->likes()->firstOrCreate([
                'user_id' => $request->user()->id,
            ]);

            if (! $like->wasRecentlyCreated) {
                return false;
            }

            $post->increment('likes_count');

            return true;
        });

        // Only on a new like — a double tap that lands twice is one like, and
        // one notice.
        if ($created) {
            $notify->like($post->user, $request->user(), $post);
        }

        return (new PostLikeResource($post->refresh(), hasLiked: true))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /**
     * Take a like back.
     *
     * Unliking a post the caller never liked is treated as already done.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        DB::transaction(function () use ($request, $post): void {
            $deleted = $post->likes()
                ->where('user_id', $request->user()->id)
                ->delete();

            if ($deleted === 0) {
                return;
            }

            $post->decrement('likes_count');
        });

        return (new PostLikeResource($post->refresh(), hasLiked: false))->response();
    }
}
