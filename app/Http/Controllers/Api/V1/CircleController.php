<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Circles\BlockFromCircle;
use App\Actions\Circles\CreateCircle;
use App\Actions\Circles\RemoveCircleMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCircleRequest;
use App\Http\Requests\Api\V1\StoreCircleRequest;
use App\Http\Requests\Api\V1\UpdateCircleRequest;
use App\Http\Resources\Api\V1\CircleMemberResource;
use App\Http\Resources\Api\V1\CircleResource;
use App\Http\Resources\Api\V1\UserSummaryResource;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CircleController extends Controller
{
    /**
     * How many inner circles one listing carries.
     *
     * A ceiling rather than a page: a circle with more inside it than this has
     * outgrown the shape, and paginating them would be a way of not saying so.
     */
    protected const MAX_SUB_CIRCLES = 50;

    /**
     * The circles this person is in — the ones they made and the ones they
     * joined, newest first.
     *
     * Both in one list rather than two endpoints: the screen shows them
     * together, and `is_owner` on each row is the only thing that separates
     * them.
     *
     * @return AnonymousResourceCollection<int, CircleResource>
     */
    public function index(IndexCircleRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $circles = Circle::query()
            ->where(function (Builder $query) use ($viewer): void {
                $query
                    ->where('owner_id', $viewer->getKey())
                    ->orWhereHas(
                        'memberships',
                        fn (Builder $member) => $member->where('user_id', $viewer->getKey()),
                    );
            })
            ->withExists([
                'memberships as is_member' => fn (Builder $member) => $member
                    ->where('user_id', $viewer->getKey()),
            ])
            ->withCount('posts')
            ->with('owner', 'parent')
            // The id breaks ties, so the cursor has something unique to sit on
            // when two circles are made in the same second.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return CircleResource::collection($circles);
    }

    /**
     * Start a circle.
     *
     * Whoever makes it is its first member: a circle you made but are not in
     * would need explaining, and every count on the screen would have to
     * special-case it.
     */
    public function store(StoreCircleRequest $request, CreateCircle $createCircle): JsonResponse
    {
        $parent = $this->parentFor($request->validated('parent_id'));

        $circle = $createCircle->execute($request->user(), [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tag' => $request->validated('tag'),
            'parent_id' => $parent?->getKey(),
        ]);

        $circle->setAttribute('is_member', true);

        return (new CircleResource($circle->load('owner', 'parent')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * One circle on its own, for the screen that opens it.
     */
    public function show(Request $request, Circle $circle): JsonResponse
    {
        Gate::authorize('view', $circle);

        $viewer = $request->user();

        $circle->setAttribute(
            'is_member',
            $circle->memberships()->where('user_id', $viewer->getKey())->exists(),
        );

        // The parent rides along so a sub-circle's screen can name the circle
        // it sits inside without a second request to find out.
        return (new CircleResource($circle->load('owner', 'parent')->loadCount('posts')))->response();
    }

    /**
     * Change a circle's name, what it is for, or the tag it carries.
     *
     * The owner's alone, like taking it down: the description is what the
     * circle tells everyone it is, and a group any member could rewrite is one
     * that says something different every week.
     *
     * Answers with the whole circle rather than the fields that moved, so the
     * screen behind the form can take the response as it stands instead of
     * reloading to find out what it now says.
     */
    public function update(UpdateCircleRequest $request, Circle $circle): JsonResponse
    {
        Gate::authorize('update', $circle);

        $viewer = $request->user();
        $attributes = $request->validated();

        $name = trim($attributes['name']);
        $circle->name = $name;

        /*
         * The badge letter follows the name; the colour does not.
         *
         * A circle showing an initial its name no longer has is plainly wrong,
         * so that is re-derived. The colour is how the circle is picked out of
         * a list at a glance — it was chosen from the name only because nobody
         * wanted a colour picker, not so it would follow one — and repainting
         * the banner for a rename would read as a different circle.
         */
        $circle->icon_initial = Str::upper(Str::substr($name, 0, 1));

        // Only what was actually sent: absent is "leave it", and a null is the
        // way to clear one. See `UpdateCircleRequest`.
        foreach (['description', 'tag'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $circle->{$field} = $attributes[$field];
            }
        }

        $circle->save();

        $circle->setAttribute(
            'is_member',
            $circle->memberships()->where('user_id', $viewer->getKey())->exists(),
        );

        return (new CircleResource($circle->load('owner', 'parent')->loadCount('posts')))->response();
    }

    /**
     * Who is in a circle, most recently joined first.
     *
     * @return AnonymousResourceCollection<int, CircleMemberResource>
     */
    public function members(IndexCircleRequest $request, Circle $circle): AnonymousResourceCollection
    {
        Gate::authorize('view', $circle);

        $viewer = $request->user();

        $members = $circle->memberships()
            ->with([
                'user' => fn (Relation $query) => $query
                    ->withActiveStory()
                    ->withUnseenStory($viewer)
                    ->with(['followers' => fn (Relation $followers) => $followers->whereKey($viewer->getKey())]),
                // Carried so a row can say which of them made the circle,
                // without a query per member to find out.
                'circle',
            ])
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return CircleMemberResource::collection($members);
    }

    /**
     * Join a circle.
     *
     * Joining twice is not an error and does not count twice — the unique index
     * on (user_id, circle_id) is what makes that true under a double tap.
     */
    public function join(Request $request, Circle $circle): JsonResponse
    {
        Gate::authorize('view', $circle);

        $user = $request->user();

        // Blocking has to stop the front door as well as the invitation, or it
        // only holds until the person taps Join.
        if ($circle->blocks()->where('user_id', $user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'circle' => 'You cannot join this circle.',
            ]);
        }

        DB::transaction(function () use ($user, $circle): void {
            $membership = CircleMembership::firstOrCreate(
                ['user_id' => $user->getKey(), 'circle_id' => $circle->getKey()],
                ['joined_at' => now()],
            );

            if ($membership->wasRecentlyCreated) {
                $circle->increment('members_count');
            }
        });

        return $this->membershipState($circle->refresh(), member: true);
    }

    /**
     * Leave a circle.
     *
     * Leaving one you were never in is treated as already done. The owner may
     * leave their own: they still own it, and a circle whose owner is obliged
     * to stay is one nobody can step back from.
     */
    public function leave(Request $request, Circle $circle): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $circle): void {
            $deleted = $circle->memberships()
                ->where('user_id', $user->getKey())
                ->delete();

            // Guarded, so a count that has already reached zero is not driven
            // below it by a repeated request.
            if ($deleted > 0 && $circle->members_count > 0) {
                $circle->decrement('members_count');
            }
        });

        return $this->membershipState($circle->refresh(), member: false);
    }

    /**
     * Turn somebody out of a circle.
     *
     * The owner cannot be removed, including by themselves: a circle with
     * nobody answerable for it is worse than one somebody has lost interest in.
     */
    public function removeMember(Request $request, Circle $circle, User $user, RemoveCircleMember $removeMember): JsonResponse
    {
        Gate::authorize('manage', $circle);

        $removeMember->execute($circle, $user);

        return response()->json([
            'data' => ['id' => $user->getKey(), 'members_count' => $circle->refresh()->members_count],
        ]);
    }

    /**
     * Bar somebody from a circle.
     *
     * Removing takes back this membership; blocking stops the next one. So this
     * does both, and cancels any invitation still standing — otherwise a
     * blocked person could walk back in through a message sent before.
     */
    public function block(Request $request, Circle $circle, User $user, BlockFromCircle $block): JsonResponse
    {
        Gate::authorize('manage', $circle);

        $block->execute($circle, $user, $request->user());

        return response()->json([
            'data' => [
                'id' => $user->getKey(),
                'is_blocked' => true,
                'members_count' => $circle->refresh()->members_count,
            ],
        ]);
    }

    /**
     * Let somebody back in — or rather, stop keeping them out. Unblocking does
     * not rejoin them; it only clears the bar.
     */
    public function unblock(Request $request, Circle $circle, User $user, BlockFromCircle $block): JsonResponse
    {
        Gate::authorize('manage', $circle);

        $block->undo($circle, $user);

        return response()->json([
            'data' => ['id' => $user->getKey(), 'is_blocked' => false],
        ]);
    }

    /**
     * Who has been barred from a circle.
     *
     * @return AnonymousResourceCollection<int, UserSummaryResource>
     */
    public function blocked(IndexCircleRequest $request, Circle $circle): AnonymousResourceCollection
    {
        Gate::authorize('manage', $circle);

        $blocked = User::query()
            ->whereIn('id', $circle->blocks()->select('user_id'))
            ->orderBy('full_name')
            ->orderBy('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return UserSummaryResource::collection($blocked);
    }

    /**
     * Take a circle down. Only whoever made it may.
     */
    public function destroy(Request $request, Circle $circle): JsonResponse
    {
        Gate::authorize('delete', $circle);

        $circle->delete();

        return response()->json(['data' => ['id' => $circle->getKey()]]);
    }

    /**
     * The circle a new sub-circle is being opened inside, if any.
     *
     * Validation has already established that the id names a circle the caller
     * owns. What is left is the rule the database cannot express: nesting goes
     * one level deep, so a circle already inside another cannot hold more.
     *
     * Refused rather than flattened onto the grandparent — a client asking for
     * something the model does not have should be told so, not quietly given
     * something else.
     */
    protected function parentFor(?string $parentId): ?Circle
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Circle::query()->findOrFail($parentId);

        if ($parent->isSubCircle()) {
            throw ValidationException::withMessages([
                'parent_id' => 'A sub-circle cannot hold sub-circles of its own.',
            ]);
        }

        return $parent;
    }

    /**
     * The circles inside one circle.
     *
     * Answered to anybody who may see the parent, because a sub-circle is part
     * of what the parent is: knowing a circle has a beginners' circle inside is
     * not the same as being able to read it, and the wall behind each one is
     * still its own to gate.
     *
     * Not paginated. A circle with more inside it than fits in one answer is
     * not a shape this is built for, and the cap says so rather than pretending.
     *
     * @return AnonymousResourceCollection<int, CircleResource>
     */
    public function subCircles(Request $request, Circle $circle): AnonymousResourceCollection
    {
        Gate::authorize('view', $circle);

        $viewer = $request->user();

        $inner = $circle->subCircles()
            ->withExists([
                'memberships as is_member' => fn (Builder $member) => $member
                    ->where('user_id', $viewer->getKey()),
            ])
            ->withCount('posts')
            ->with('owner', 'parent')
            ->orderBy('name')
            ->limit(self::MAX_SUB_CIRCLES)
            ->get();

        return CircleResource::collection($inner);
    }

    /**
     * Hand a sub-circle to one of its members.
     *
     * The parent's owner decides who keeps the circle they opened, and may
     * change their mind later. Only a sub-circle has this: a circle in its own right
     * answers to nobody above it, so there is nobody with standing to reassign
     * it.
     *
     * The new owner has to be in it already. Handing a circle to somebody who
     * is not in it would leave it run by an outsider, whose first act would be
     * joining the thing they already own.
     */
    public function assignOwner(Request $request, Circle $circle, User $user): JsonResponse
    {
        Gate::authorize('update', $circle);

        if (! $circle->isSubCircle()) {
            throw ValidationException::withMessages([
                'circle' => 'Only a sub-circle can be handed to somebody else.',
            ]);
        }

        if (! $circle->memberships()->where('user_id', $user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'user' => 'They have to be in the sub-circle first.',
            ]);
        }

        $circle->update(['owner_id' => $user->getKey()]);

        return (new CircleResource($circle->fresh()?->load('owner')))->response();
    }

    /**
     * The answer both join and leave give: what the circle looks like after.
     */
    protected function membershipState(Circle $circle, bool $member): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $circle->getKey(),
                'is_member' => $member,
                'members_count' => $circle->members_count,
            ],
        ]);
    }
}
