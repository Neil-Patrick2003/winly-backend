<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PostLikeController extends Controller
{
    /**
     * Like a post from the web app.
     *
     * The API has its own like endpoint for the phone; this one answers the
     * session-authenticated web app and redirects back the way Inertia expects
     * rather than returning JSON.
     */
    public function store(Request $request, Post $post): RedirectResponse
    {
        $this->ensureVisible($request, $post);

        DB::transaction(function () use ($request, $post): void {
            $like = $post->likes()->firstOrCreate(['user_id' => $request->user()->id]);

            if ($like->wasRecentlyCreated) {
                $post->increment('likes_count');
            }
        });

        return back();
    }

    /**
     * Take a like back.
     */
    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->ensureVisible($request, $post);

        DB::transaction(function () use ($request, $post): void {
            $deleted = $post->likes()->where('user_id', $request->user()->id)->delete();

            if ($deleted > 0) {
                $post->decrement('likes_count');
            }
        });

        return back();
    }

    /**
     * Refuse a post the reader was never shown.
     *
     * A post shared into a circle was addressed to that circle, so somebody
     * outside it cannot reach past the feed to like it.
     */
    protected function ensureVisible(Request $request, Post $post): void
    {
        abort_unless($post->isVisibleTo($request->user()), Response::HTTP_FORBIDDEN);
    }
}
