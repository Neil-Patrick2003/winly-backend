<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexStoryViewRequest;
use App\Http\Requests\Api\V1\StoreStoryRequest;
use App\Http\Resources\Api\V1\StoryReelResource;
use App\Http\Resources\Api\V1\StoryResource;
use App\Http\Resources\Api\V1\StoryViewerResource;
use App\Models\Story;
use App\Models\StoryReaction;
use App\Models\StoryView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    /**
     * The disk story photos are written to.
     */
    protected const DISK = 'public';

    /**
     * The folder within that disk.
     */
    protected const FOLDER = 'stories';

    /**
     * The stories worth showing this reader, grouped by who posted them.
     *
     * Yours and the people you follow — a story is shared with an audience,
     * not broadcast, so a stranger's does not belong here. Expired ones are
     * excluded by the `active` scope rather than deleted: they stay in the
     * table as a record, and simply stop being served.
     *
     * Not paginated. The window is 24 hours and the audience is people you
     * chose, so the set is small and bounded by definition; a cursor would be
     * ceremony around a list that fits in one response.
     */
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        $stories = Story::query()
            ->active()
            ->where(function (Builder $query) use ($viewer): void {
                $query
                    ->whereIn('user_id', $viewer->following()->select('users.id'))
                    ->orWhere('user_id', $viewer->getKey());
            })
            ->with([
                'user',
                // Constrained to the reader, so a run of stories costs one
                // extra query rather than one per story.
                'viewerView' => fn (Relation $query) => $query->where('viewer_id', $viewer->getKey()),
                // Likewise constrained: the reader's own reaction, so the
                // viewer opens with theirs already lit.
                'viewerReaction' => fn (Relation $query) => $query->where('user_id', $viewer->getKey()),
                /*
                 * The reactions on the reader's own stories, so the poster can
                 * be shown which ones came in. Narrowed to their stories here
                 * rather than filtered in the resource: the rest of the reel
                 * belongs to other people, and there is no reason to carry
                 * their reactions across the wire to be discarded.
                 */
                'reactions' => fn (Relation $query) => $query
                    ->select('id', 'story_id', 'reaction_type')
                    ->whereHas('story', fn (Builder $owner) => $owner->where('user_id', $viewer->getKey())),
            ])
            // Subqueries on the statement already being run, so counting costs
            // nothing per story. The resource shows these only to the poster.
            ->withCount(['views', 'reactions'])
            // Oldest first within a person: a reel is watched in the order it
            // was posted.
            ->orderBy('created_at')
            ->get();

        $reels = $stories
            ->groupBy('user_id')
            ->map(fn (Collection $run): StoryReelResource => new StoryReelResource(
                $run->first()->user,
                $run
            ))
            ->values()
            // The reader's own reel first, then whoever has something unwatched,
            // then by whoever posted most recently.
            ->sortBy([
                fn (StoryReelResource $reel): int => $reel->resource->is($viewer) ? 0 : 1,
                fn (StoryReelResource $reel): int => $reel->toArray($request)['has_unseen'] ? 0 : 1,
            ])
            ->values();

        return response()->json(['data' => $reels]);
    }

    /**
     * Mark a story as seen by the reader.
     *
     * Idempotent: watching the same story twice is not two views, and a client
     * that loses track may say so again without consequence. Watching your own
     * story does not count — the unique index would allow it, but a view count
     * that includes the poster is not what anyone means by one.
     */
    public function view(Request $request, Story $story): JsonResponse
    {
        $viewer = $request->user();

        if (! $story->user->is($viewer)) {
            StoryView::firstOrCreate(
                ['story_id' => $story->getKey(), 'viewer_id' => $viewer->getKey()],
                ['viewed_at' => now()],
            );
        }

        return response()->json([
            'data' => [
                'id' => $story->getKey(),
                'views_count' => $story->views()->count(),
            ],
        ]);
    }

    /**
     * Who has watched a story, most recent first.
     *
     * Paginated where the list endpoint is not, because this one has no bound:
     * a story from someone with a large following can be watched by all of
     * them, and there is no 24-hour window narrowing it.
     *
     * @return AnonymousResourceCollection<int, StoryViewerResource>
     */
    public function viewers(IndexStoryViewRequest $request, Story $story): AnonymousResourceCollection
    {
        Gate::authorize('viewers', $story);

        $reader = $request->user();

        $views = $story->views()
            ->select('story_views.*')
            /*
             * What each of them left on the way past, if anything.
             *
             * A correlated subquery rather than a relation, because a view and
             * a reaction are separate rows with no link between them beyond
             * being the same person on the same story — joining would drop
             * anyone who watched without reacting, which is most of them.
             */
            ->addSelect(['reaction_type' => StoryReaction::query()
                ->select('reaction_type')
                ->whereColumn('story_reactions.user_id', 'story_views.viewer_id')
                ->whereColumn('story_reactions.story_id', 'story_views.story_id')
                ->limit(1),
            ])
            ->with([
                'viewer' => fn (Relation $query) => $query
                    ->withActiveStory()
                    ->withUnseenStory($reader)
                    ->with(['followers' => fn (Relation $followers) => $followers->whereKey($reader->getKey())]),
            ])
            // The view id breaks ties, so a cursor still has something unique
            // to sit on when two people watch within the same second.
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return StoryViewerResource::collection($views);
    }

    /**
     * Share a story.
     *
     * The expiry is set here rather than accepted from the caller: how long a
     * story lasts is the product's decision, not the client's.
     */
    public function store(StoreStoryRequest $request): JsonResponse
    {
        $path = $request->file('image')->store(self::FOLDER, self::DISK);

        $story = $request->user()->stories()->create([
            'image_url' => url(Storage::disk(self::DISK)->url($path)),
            'caption' => $request->validated('caption'),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ]);

        return (new StoryResource($story))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Take a story down before it expires.
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        Gate::authorize('delete', $story);

        $story->delete();

        return response()->json(['data' => ['id' => $story->id]]);
    }
}
