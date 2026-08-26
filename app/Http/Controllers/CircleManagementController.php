<?php

namespace App\Http\Controllers;

use App\Actions\Circles\BlockFromCircle;
use App\Actions\Circles\CreateCircle;
use App\Actions\Circles\InviteToCircle;
use App\Actions\Circles\RemoveCircleMember;
use App\Actions\Circles\SetCircleMemberRole;
use App\Http\Requests\Api\V1\StoreCircleRequest;
use App\Http\Requests\UpdateCircleRequest;
use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CircleManagementController extends Controller
{
    /**
     * How many members the management list shows before paging.
     */
    protected const PER_PAGE = 20;

    /**
     * How many people the invite picker offers at once.
     */
    protected const CANDIDATE_LIMIT = 25;

    /**
     * Show the circle's settings.
     */
    public function edit(Request $request, Circle $circle): Response
    {
        Gate::authorize('manage', $circle);

        $circle->load('owner');

        return Inertia::render('circles/manage', [
            'circle' => [
                'id' => $circle->id,
                'name' => $circle->name,
                'description' => $circle->description,
                'icon_initial' => $circle->icon_initial,
                'color_hex' => $circle->color_hex,
                'tag' => $circle->tag,
                'members_count' => $circle->members_count,
                'is_private' => $circle->is_private,
                'can_manage' => true,
                /*
                 * Handing the circle to somebody else is staff's alone, not the
                 * owner's. An owner giving their circle away is a different
                 * feature with different questions attached — this one exists
                 * for the circle whose owner has gone quiet, and the person who
                 * has gone quiet is not the one who will click it.
                 */
                'can_transfer_ownership' => (bool) $request->user()?->is_admin,
                'owner' => $circle->owner === null ? null : [
                    'id' => $circle->owner->id,
                    'full_name' => $circle->owner->full_name,
                ],
            ],
            'members' => $circle->memberships()
                ->with('user')
                ->orderBy('joined_at')
                ->paginate(self::PER_PAGE)
                ->through(fn (CircleMembership $membership): array => [
                    'id' => $membership->user->id,
                    'full_name' => $membership->user->full_name,
                    'username' => $membership->user->username,
                    'avatar_url' => $membership->user->avatar_url,
                    'joined_at' => $membership->joined_at->toIso8601String(),
                    'is_owner' => $membership->user_id === $circle->owner_id,
                    /*
                     * Runs the circle without having made it.
                     *
                     * Kept apart from `is_owner` because the two are not the
                     * same offer: the founder cannot be demoted and does not
                     * get the control, and somebody given the rank can be.
                     */
                    'is_co_owner' => $membership->role === CircleMembership::ROLE_OWNER
                        && $membership->user_id !== $circle->owner_id,
                ]),
            /*
             * The circles inside this one.
             *
             * Only for a circle that stands on its own — one that already sits
             * inside another cannot hold more, so the card does not appear and
             * there is nothing to explain about why it is empty.
             */
            'subCircles' => $circle->isSubCircle() ? [] : $circle->subCircles()
                ->with('owner')
                ->orderBy('name')
                ->get()
                ->map(fn (Circle $sub): array => [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'members_count' => $sub->members_count,
                    'owner' => $sub->owner === null ? null : [
                        'id' => $sub->owner->id,
                        'full_name' => $sub->owner->full_name,
                    ],
                ])
                ->all(),
            // Camel-cased to match `subCircles` above and the prop the page
            // destructures. Sent as snake_case it arrived as `undefined`, which
            // is falsy — so the card silently never rendered.
            'canAddSubCircle' => ! $circle->isSubCircle(),
            'invitations' => $this->pendingInvitations($circle),
            'blocked' => $this->blocked($circle),
            'candidates' => $this->candidates($request, $circle),
            'search' => $request->string('search')->value(),
        ]);
    }

    /**
     * Save the circle's settings.
     */
    public function update(UpdateCircleRequest $request, Circle $circle): RedirectResponse
    {
        Gate::authorize('manage', $circle);

        $circle->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Circle updated.')]);

        return back();
    }

    /**
     * Open a circle inside this one.
     *
     * The owner's, and the website's: a sub-circle decides who ends up able to
     * read a group's wins, and the phone deliberately does not offer it.
     *
     * Whoever creates it keeps it to begin with. Handing it to somebody else is
     * a second, separate step — the list on this page is where that is done.
     */
    public function createSubCircle(
        StoreCircleRequest $request,
        Circle $circle,
        CreateCircle $createCircle,
    ): RedirectResponse {
        Gate::authorize('manage', $circle);

        if ($circle->isSubCircle()) {
            throw ValidationException::withMessages([
                'name' => __('This circle already sits inside another one.'),
            ]);
        }

        $createCircle->execute($request->user(), [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tag' => $request->validated('tag'),
            'parent_id' => $circle->getKey(),
            // Its own answer rather than the parent's: a private circle inside
            // a public one is the whole point of the smaller room, and a public
            // one inside a private parent is still reached only through it.
            'is_private' => $request->boolean('is_private'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Circle created inside this one.')]);

        return back();
    }

    /**
     * Ask somebody to join.
     *
     * Only people already on welle can be asked this way — an invitation is a
     * row against a user. Bringing somebody who has no account yet is a
     * different thing, and belongs to a share link rather than to this form.
     */
    public function invite(Request $request, Circle $circle, InviteToCircle $invite): RedirectResponse
    {
        Gate::authorize('invite', $circle);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::notIn([$request->user()->getKey()]),
            ],
        ], [
            'user_id.not_in' => 'You are already in this circle.',
            'user_id.exists' => 'That person is no longer around.',
        ]);

        $invitee = User::query()->whereKey($validated['user_id'])->firstOrFail();

        $invite->execute($circle, $request->user(), $invitee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    /**
     * Take back an invitation that has not been answered.
     */
    public function revokeInvitation(Circle $circle, CircleInvitation $invitation): RedirectResponse
    {
        Gate::authorize('manage', $circle);

        abort_unless($invitation->circle_id === $circle->getKey(), 404);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Invitation taken back.')]);

        return back();
    }

    /**
     * Turn a member out of the circle.
     */
    public function removeMember(Circle $circle, User $user, RemoveCircleMember $remove): RedirectResponse
    {
        Gate::authorize('manage', $circle);

        $remove->execute($circle, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    /**
     * Give somebody in the circle the run of it, or take it back.
     *
     * The owner's own screen, not staff's: who helps run a group is the group's
     * business, and unlike handing the circle over it is a decision the person
     * in charge is still around to make.
     */
    public function setMemberRole(
        Request $request,
        Circle $circle,
        User $user,
        SetCircleMemberRole $setRole,
    ): RedirectResponse {
        Gate::authorize('manage', $circle);

        $role = $request->validate([
            'role' => ['required', 'string', Rule::in(CircleMembership::ROLES)],
        ])['role'];

        $setRole->execute($circle, $user, $role);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $role === CircleMembership::ROLE_OWNER
                ? __('They can now run this circle.')
                : __('They are an ordinary member again.'),
        ]);

        return back();
    }

    /**
     * Bar somebody from the circle.
     */
    public function block(Request $request, Circle $circle, User $user, BlockFromCircle $block): RedirectResponse
    {
        Gate::authorize('manage', $circle);

        $block->execute($circle, $user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member blocked.')]);

        return back();
    }

    /**
     * Clear the bar. It does not put them back in.
     */
    public function unblock(Circle $circle, User $user, BlockFromCircle $block): RedirectResponse
    {
        Gate::authorize('manage', $circle);

        $block->undo($circle, $user);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Member unblocked.')]);

        return back();
    }

    /**
     * Take the circle down.
     */
    public function destroy(Circle $circle): RedirectResponse
    {
        Gate::authorize('delete', $circle);

        $circle->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Circle deleted.')]);

        return to_route('circles.index');
    }

    /**
     * The invitations still waiting on an answer.
     *
     * @return list<array<string, mixed>>
     */
    protected function pendingInvitations(Circle $circle): array
    {
        return array_values($circle->invitations()
            ->pending()
            ->with('invitee')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CircleInvitation $invitation): array => [
                'id' => $invitation->id,
                'invited_at' => $invitation->created_at?->toIso8601String(),
                'user' => [
                    'id' => $invitation->invitee->id,
                    'full_name' => $invitation->invitee->full_name,
                    'username' => $invitation->invitee->username,
                    'avatar_url' => $invitation->invitee->avatar_url,
                ],
            ])
            ->all());
    }

    /**
     * Who has been barred.
     *
     * @return list<array<string, mixed>>
     */
    protected function blocked(Circle $circle): array
    {
        return array_values(User::query()
            ->whereIn('id', $circle->blocks()->select('user_id'))
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ])
            ->all());
    }

    /**
     * Who the owner can ask, and where each of them already stands.
     *
     * Mutual follows only, matching the app: following somebody is not a
     * relationship either of you agreed to, and the mutual case is the one
     * people mean when they say friend.
     *
     * Everyone comes back, those already in and already asked included, each
     * with a status. A list that quietly dropped them would look like the
     * invitation had never been sent.
     *
     * @return list<array<string, mixed>>
     */
    protected function candidates(Request $request, Circle $circle): array
    {
        $viewer = $request->user();
        $search = $request->string('search')->trim()->value();
        // Fetched once rather than asked per row, which would be a query per
        // candidate to answer a question the whole list shares.
        $blockedIds = $circle->blocks()->pluck('user_id')->all();

        /*
         * Friends only, unless staff are asking.
         *
         * An invitation from a stranger is unwelcome, so an owner may only ask
         * people they and the person both follow. Staff are not on this screen
         * as a member — they are here for a circle nobody has asked them into,
         * and being unable to seat somebody in it because the two of them do
         * not follow each other would be the wrong rule applied to the wrong
         * person. Nothing else about the invitation changes: it is still an
         * ask, and the invitee still has to accept it.
         */
        return array_values(User::query()
            ->whereKeyNot($viewer->getKey())
            ->when(! $viewer->is_admin, fn (Builder $query) => $query
                ->whereIn('id', $viewer->following()->select('users.id'))
                ->whereIn('id', $viewer->followers()->select('users.id')))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('full_name', 'like', $term)
                    ->orWhere('username', 'like', $term));
            })
            ->withExists([
                'circleMemberships as is_member' => fn (Builder $query) => $query
                    ->where('circle_id', $circle->getKey()),
            ])
            ->addSelect(['invite_status' => CircleInvitation::query()
                ->select('status')
                ->whereColumn('circle_invitations.invitee_id', 'users.id')
                ->where('circle_invitations.circle_id', $circle->getKey())
                ->limit(1),
            ])
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'is_member' => (bool) $user->getAttribute('is_member'),
                'invite_status' => $user->getAttribute('invite_status'),
                'is_blocked' => in_array($user->getKey(), $blockedIds, strict: true),
            ])
            ->all());
    }
}
