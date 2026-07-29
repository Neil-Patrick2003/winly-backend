<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCommentRequest;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Http\Requests\Api\V1\UpdateCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * List the comments on a post, newest first.
     *
     * The freshest reply sits at the top and paging forward walks back through
     * the thread. Cursor rather than offset paging: comments left while a
     * reader is partway down land above the cursor, so they neither repeat a
     * row nor push one out of reach.
     *
     * @return AnonymousResourceCollection<int, CommentResource>
     */
    public function index(IndexCommentRequest $request, Post $post): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $comments = $post->comments()
            ->with([
                'user' => fn (Relation $query) => $query->withActiveStory(),
                'user.followers' => fn (Relation $query) => $query->whereKey($viewer->getKey()),
            ])
            ->latestFirst()
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return CommentResource::collection($comments);
    }

    /**
     * Leave a comment on a post.
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = DB::transaction(function () use ($request, $post): Comment {
            $comment = $post->comments()->create([
                'user_id' => $request->user()->id,
                'text' => $request->validated('text'),
            ]);

            $post->increment('comments_count');

            return $comment;
        });

        $comment->setRelation('user', $request->user());

        return (new CommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reword a comment.
     *
     * Only the text can change. Moving a comment to another post, or crediting
     * it to somebody else, is not an edit.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);

        $comment->update(['text' => $request->validated('text')]);

        $comment->load('user');

        return (new CommentResource($comment))->response();
    }

    /**
     * Delete a comment.
     *
     * The running total travels back with the deletion so the client can
     * settle on the post's new count without refetching it.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $post = $comment->post;

        DB::transaction(function () use ($comment, $post): void {
            $comment->delete();

            $post->decrement('comments_count');
        });

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'post_id' => $post->id,
                'comments_count' => $post->refresh()->comments_count,
            ],
        ]);
    }
}
