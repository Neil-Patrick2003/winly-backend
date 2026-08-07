<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPostLikeRequest;
use App\Http\Resources\Api\V1\PostLikeResource;
use App\Http\Resources\Api\V1\PostLikerResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostLikeController extends Controller
{
    /**
     * List who liked a post, most recent first.
     *
     * Gated on reading the post rather than on owning it, which is the
     * difference between this and a story's viewer list: who watched something
     * that disappears in a day is the poster's business alone, while a like is
     * a public act on a post — anybody who can see the post can see it was
     * liked, so telling them by whom reveals nothing the count did not.
     *
     * @return AnonymousResourceCollection<int, PostLikerResource>
     */
    public function index(IndexPostLikeRequest $request, Post $post): AnonymousResourceCollection
    {
        Gate::authorize('view', $post);

        $reader = $request->user();

        $likes = $post->likes()
            ->with(['user' => fn (Relation $query) => $query
                ->withActiveStory()
                ->withUnseenStory($reader)
                ->with(['followers' => fn (Relation $followers) => $followers->whereKey($reader->getKey())]),
            ])
            // The like id breaks ties, so a cursor still has something unique
            // to sit on when two people tap within the same second.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostLikerResource::collection($likes);
    }

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
