<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiscoverRequest;
use App\Http\Resources\Api\V1\CircleResource;
use App\Http\Resources\Api\V1\SuggestedPersonResource;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class DiscoverController extends Controller
{
    /**
     * How many of each the page carries.
     *
     * Discover is a shop window, not a catalogue: a short list somebody reads
     * to the end is worth more than a long one they scroll past. Anyone who
     * wants more is looking for something in particular, which is what the
     * search box is for.
     */
    protected const LIMIT = 10;

    /**
     * Circles worth joining, people worth following, and the tags to sift by.
     *
     * One response rather than three endpoints: the screen shows all of it at
     * once and neither list is paginated, so three round trips would only make
     * the page assemble itself in pieces.
     */
    public function index(DiscoverRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $term = $request->validated('q');
        $tag = $request->validated('tag');

        /*
         * Biggest first, which is what "trending" means with nothing to measure
         * a trend against yet — there is no history of joins to rank by. The id
         * breaks ties so the order does not shuffle between requests.
         */
        $circles = Circle::query()
            ->where('is_private', false)
            ->search($term)
            ->taggedWith($tag)
            ->withExists([
                'memberships as is_member' => fn (Builder $query) => $query
                    ->where('user_id', $viewer->getKey()),
            ])
            ->with('parent')
            ->withCount('posts')
            ->orderByDesc('members_count')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        /*
         * The people who have posted the most, which is the only signal the app
         * has for who is worth following — there is no engagement history to
         * rank by, and alphabetical would be no suggestion at all.
         *
         * Counted from posts rather than read off `wins_count`: that column
         * counts wins, and a post carrying all three pillars moves it by three.
         * Someone who logs meditation, learning and movement together every
         * morning is not three times the poster of someone who writes three
         * separate mornings, which is what ranking by it would claim.
         *
         * Anyone with nothing posted is left out of the *suggestions* rather
         * than padding them: proposing an empty account is worse than proposing
         * nobody, so the list runs short instead.
         *
         * Searching is the other thing entirely. Somebody typing a name is
         * looking for a person, not for a recommendation, and answering "no
         * results" because that person has not posted yet is answering a
         * question they did not ask — most of all for a friend who has only
         * just joined, which is exactly when you go looking for them.
         */
        $searching = filled($term);

        $people = User::query()
            ->whereKeyNot($viewer->getKey())
            /*
             * Both narrowings belong to the shop window rather than to the
             * search box, and for the same reason: somebody you already follow
             * is not worth *recommending*, but they are absolutely worth
             * finding. Searching a friend's name and getting nothing back
             * because you already follow them reads as the search being broken.
             *
             * The rows carry `is_following` either way, so a result that is
             * already followed says so instead of offering to follow again.
             */
            ->when(! $searching, fn (Builder $query) => $query
                ->whereNotIn('id', $viewer->following()->select('users.id'))
                ->has('posts'))
            ->withCount('posts')
            ->when($searching, function (Builder $query) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $term).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('full_name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
            })
            ->withActiveStory()
            ->withUnseenStory($viewer)
            ->with(['followers' => fn (Relation $query) => $query->whereKey($viewer->getKey())])
            ->orderByDesc('posts_count')
            ->orderByDesc('streak_days')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'data' => [
                /*
                 * Every tag in use, not only those on the circles above — the
                 * chips are how somebody reaches the ones that did not make it.
                 *
                 * Public circles only, like the list itself. A tag worn by
                 * nothing else is a private circle announcing its subject, and
                 * a chip that selects an empty list is the app saying there is
                 * something here you cannot see.
                 */
                'tags' => Circle::query()
                    ->where('is_private', false)
                    ->whereNotNull('tag')
                    ->distinct()
                    ->orderBy('tag')
                    ->pluck('tag'),
                'circles' => CircleResource::collection($circles),
                'people' => SuggestedPersonResource::collection($people),
            ],
        ]);
    }
}
