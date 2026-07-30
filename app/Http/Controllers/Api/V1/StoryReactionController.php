<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStoryReactionRequest;
use App\Models\Story;
use App\Models\StoryReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryReactionController extends Controller
{
    /**
     * React to a story, or change the reaction already left.
     *
     * A PUT rather than a POST because it asserts a state rather than adding
     * to a collection: one person holds one reaction to one story, and sending
     * the same one twice leaves things exactly as they were. The unique index
     * on (story_id, user_id) is what makes that true under a double tap.
     *
     * Reacting to your own story is allowed. It is a strange thing to do, but
     * it is nobody's business to refuse, and unlike a view it does not inflate
     * a number the poster is reading about other people.
     */
    public function store(StoreStoryReactionRequest $request, Story $story): JsonResponse
    {
        $reaction = StoryReaction::updateOrCreate(
            ['story_id' => $story->getKey(), 'user_id' => $request->user()->getKey()],
            ['reaction_type' => $request->validated('reaction_type')],
        );

        return response()->json([
            'data' => [
                'id' => $story->getKey(),
                'viewer_reaction' => $reaction->reaction_type,
                'reactions_count' => $story->reactions()->count(),
            ],
        ], $reaction->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Take a reaction back.
     *
     * Removing one that was never there is treated as already done, so a
     * client that lost track of its own state does not get an error for
     * arriving at the outcome it wanted.
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        $story->reactions()
            ->where('user_id', $request->user()->getKey())
            ->delete();

        return response()->json([
            'data' => [
                'id' => $story->getKey(),
                'viewer_reaction' => null,
                'reactions_count' => $story->reactions()->count(),
            ],
        ]);
    }
}
