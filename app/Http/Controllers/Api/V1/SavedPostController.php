<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Keeping a post to come back to.
 *
 * Nothing here notifies anybody or moves a counter, which is what separates a
 * save from a like: it is a private shelf, and the person who wrote the post is
 * never told it is on one.
 */
class SavedPostController extends Controller
{
    /**
     * Save a post.
     *
     * Saving twice is not an error and does not save it twice — the unique
     * index on (user_id, post_id) is what makes that true under a double tap.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $save = SavedPost::firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'post_id' => $post->getKey(),
        ]);

        return $this->state($post, saved: true)
            ->setStatusCode($save->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Take a post off the shelf.
     *
     * Unsaving one that was never saved is treated as already done, so a client
     * that has lost track can simply say what it wants to be true.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $post->saves()
            ->where('user_id', $request->user()->getKey())
            ->delete();

        return $this->state($post, saved: false);
    }

    /**
     * The answer both directions give: where the reader stands with this post.
     *
     * No count of any kind — see the class comment.
     */
    protected function state(Post $post, bool $saved): JsonResponse
    {
        return response()->json([
            'data' => [
                'post_id' => $post->getKey(),
                'viewer_has_saved' => $saved,
            ],
        ]);
    }
}
