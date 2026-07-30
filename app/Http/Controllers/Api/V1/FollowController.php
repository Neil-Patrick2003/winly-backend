<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexFollowRequest;
use App\Http\Resources\Api\V1\FollowStateResource;
use App\Http\Resources\Api\V1\UserSummaryResource;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowController extends Controller
{
    /**
     * List the people this user follows, most recently followed first.
     *
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    public function following(IndexFollowRequest $request, User $user): AnonymousResourceCollection
    {
        return $this->page($request, $user, 'follower_id', 'followee_id');
    }

    /**
     * List the people who follow this user, most recent first.
     *
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    public function followers(IndexFollowRequest $request, User $user): AnonymousResourceCollection
    {
        return $this->page($request, $user, 'followee_id', 'follower_id');
    }

    /**
     * Page one side of the follow graph.
     *
     * The users are queried through a join rather than paginating the follows
     * and swapping in the people afterwards: a cursor is built from the rows
     * the paginator actually holds, so transforming them after the fact hands
     * the next page a cursor describing the wrong table.
     *
     * @param  'follower_id'|'followee_id'  $subjectSide  The column matching the subject.
     * @param  'follower_id'|'followee_id'  $personSide  The column holding the other party.
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    protected function page(
        IndexFollowRequest $request,
        User $user,
        string $subjectSide,
        string $personSide,
    ): AnonymousResourceCollection {
        $viewer = $request->user();

        $people = User::query()
            ->join('follows', "follows.{$personSide}", '=', 'users.id')
            ->where("follows.{$subjectSide}", $user->getKey())
            ->select('users.*')
            // Aliased onto the model so the cursor can read the columns it
            // orders by. The follow id breaks ties on the timestamp.
            ->addSelect([
                'follows.created_at as followed_at',
                'follows.id as follow_id',
            ])
            ->withActiveStory()
            ->withUnseenStory($viewer)
            ->with(['followers' => fn (Relation $query) => $query->whereKey($viewer->getKey())])
            ->orderByDesc('followed_at')
            ->orderByDesc('follow_id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return UserSummaryResource::collection($people);
    }

    /**
     * Follow a user.
     *
     * Following twice is not an error, it just does not count twice: the row
     * and both counters only move when the follow is new.
     */
    public function store(Request $request, User $user, RecordNotification $notify): JsonResponse
    {
        $follower = $request->user();

        $this->refuseSelfFollow($follower, $user);

        $created = DB::transaction(function () use ($follower, $user): bool {
            $follow = Follow::firstOrCreate([
                'follower_id' => $follower->id,
                'followee_id' => $user->id,
            ]);

            if (! $follow->wasRecentlyCreated) {
                return false;
            }

            $follower->increment('following_count');
            $user->increment('followers_count');

            return true;
        });

        // Only on a new follow: re-following someone you already follow is a
        // no-op, and should not put anything back on their alerts.
        if ($created) {
            $notify->follow($user, $follower);
        }

        return (new FollowStateResource($user->refresh(), isFollowing: true))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /**
     * Stop following a user.
     *
     * Unfollowing someone the caller never followed is treated as already
     * done rather than as a mistake, so a client that lost track can retry.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        $this->refuseSelfFollow($follower, $user);

        DB::transaction(function () use ($follower, $user): void {
            $deleted = Follow::query()
                ->where('follower_id', $follower->id)
                ->where('followee_id', $user->id)
                ->delete();

            if ($deleted === 0) {
                return;
            }

            $follower->decrement('following_count');
            $user->decrement('followers_count');
        });

        return (new FollowStateResource($user->refresh(), isFollowing: false))
            ->response();
    }

    /**
     * Reject the one relationship a follow may never describe.
     *
     * @throws ValidationException
     */
    protected function refuseSelfFollow(User $follower, User $followee): void
    {
        if ($follower->is($followee)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot follow yourself.',
            ]);
        }
    }
}
