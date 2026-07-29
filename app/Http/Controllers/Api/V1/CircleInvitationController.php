<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Circles\InviteToCircle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCircleRequest;
use App\Http\Requests\Api\V1\StoreCircleInvitationRequest;
use App\Http\Resources\Api\V1\CircleInvitationResource;
use App\Http\Resources\Api\V1\InvitableFriendResource;
use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CircleInvitationController extends Controller
{
    /**
     * The people this member could invite: their friends.
     *
     * A friend is a follow that goes both ways. Following someone is a one-way
     * interest — inviting a stranger who happens to follow you into a group is
     * not something either of you asked for, and the mutual case is the one
     * people mean when they say friend.
     *
     * Everyone comes back, including those already in and those already asked,
     * with a status saying which. A list that quietly dropped them would look
     * like the invitation had not been sent.
     *
     * @return AnonymousResourceCollection<int, InvitableFriendResource>
     */
    public function friends(IndexCircleRequest $request, Circle $circle): AnonymousResourceCollection
    {
        Gate::authorize('invite', $circle);

        $viewer = $request->user();

        $friends = User::query()
            ->whereIn('id', $viewer->following()->select('users.id'))
            ->whereIn('id', $viewer->followers()->select('users.id'))
            ->withExists([
                'circleMemberships as is_member' => fn (Builder $query) => $query
                    ->where('circle_id', $circle->getKey()),
            ])
            // The status of any invitation this circle has already sent them,
            // as a subquery: a relation would drag every circle's invitations
            // along to find the one that matters.
            ->addSelect(['invite_status' => CircleInvitation::query()
                ->select('status')
                ->whereColumn('circle_invitations.invitee_id', 'users.id')
                ->where('circle_invitations.circle_id', $circle->getKey())
                ->limit(1),
            ])
            ->withActiveStory()
            ->withUnseenStory($viewer)
            ->orderBy('full_name')
            ->orderBy('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return InvitableFriendResource::collection($friends);
    }

    /**
     * Ask somebody to join.
     *
     * Re-asking someone who declined replaces their answer rather than adding a
     * second row, so the unique index holds and a circle cannot be used to
     * pester. Asking someone already in is refused outright — the button that
     * sent it should not have been there.
     */
    public function store(StoreCircleInvitationRequest $request, Circle $circle, InviteToCircle $invite): JsonResponse
    {
        Gate::authorize('invite', $circle);

        $inviter = $request->user();
        // `firstOrFail` rather than `findOrFail`: the latter is typed as
        // returning a model or a collection, so everything downstream of it
        // becomes a union that has no `getKey`.
        $invitee = User::query()->whereKey($request->validated('user_id'))->firstOrFail();

        $invitation = $invite->execute($circle, $inviter, $invitee);

        return (new CircleInvitationResource($invitation->load(['circle', 'inviter'])))
            ->response()
            ->setStatusCode($invitation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * The invitations waiting on the caller.
     *
     * This is what the alerts screen shows. Only pending ones: an invitation
     * already answered is not news, and the circle it led to is in your list.
     *
     * @return AnonymousResourceCollection<int, CircleInvitationResource>
     */
    public function index(IndexCircleRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $invitations = $viewer->circleInvitations()
            ->pending()
            ->with([
                'circle',
                'inviter' => fn (Relation $query) => $query->withActiveStory(),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return CircleInvitationResource::collection($invitations);
    }

    /**
     * Take up an invitation, which is what actually puts you in the circle.
     *
     * Being asked is not being in: until this runs there is no membership, and
     * the circle does not appear in the invitee's list.
     */
    public function accept(Request $request, CircleInvitation $invitation): JsonResponse
    {
        Gate::authorize('respond', $invitation);

        $circle = $invitation->circle;

        if ($circle->blocks()->where('user_id', $invitation->invitee_id)->exists()) {
            throw ValidationException::withMessages([
                'invitation' => 'You cannot join this circle.',
            ]);
        }

        DB::transaction(function () use ($invitation, $circle): void {
            $membership = CircleMembership::firstOrCreate(
                ['user_id' => $invitation->invitee_id, 'circle_id' => $circle->getKey()],
                ['joined_at' => now()],
            );

            if ($membership->wasRecentlyCreated) {
                $circle->increment('members_count');
            }

            $invitation->update([
                'status' => CircleInvitation::ACCEPTED,
                'responded_at' => now(),
            ]);
        });

        return (new CircleInvitationResource($invitation->fresh()->load(['circle', 'inviter'])))
            ->response();
    }

    /**
     * Turn one down.
     *
     * Kept as a declined row rather than deleted, so the circle can ask again
     * later without the unique index standing in the way — and so a second ask
     * replaces the first rather than piling up.
     */
    public function decline(Request $request, CircleInvitation $invitation): JsonResponse
    {
        Gate::authorize('respond', $invitation);

        $invitation->update([
            'status' => CircleInvitation::DECLINED,
            'responded_at' => now(),
        ]);

        return (new CircleInvitationResource($invitation->fresh()->load(['circle', 'inviter'])))
            ->response();
    }
}
