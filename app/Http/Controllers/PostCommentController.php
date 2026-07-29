<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PostCommentController extends Controller
{
    /**
     * Leave a comment from the web app.
     *
     * The rules come from the API's request class so a comment means the same
     * thing wherever it was written.
     */
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        abort_unless($post->isVisibleTo($request->user()), Response::HTTP_FORBIDDEN);

        DB::transaction(function () use ($request, $post): void {
            $post->comments()->create([
                'user_id' => $request->user()->id,
                'text' => $request->validated('text'),
            ]);

            $post->increment('comments_count');
        });

        return back();
    }

    /**
     * Delete a comment.
     */
    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $post = $comment->post;

        DB::transaction(function () use ($comment, $post): void {
            $comment->delete();

            $post->decrement('comments_count');
        });

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Comment deleted.')]);

        return back();
    }
}
