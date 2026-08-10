<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Circles\CreateCircle;
use App\Concerns\DescribesCircles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCircleRequest;
use App\Models\Circle;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CircleController extends Controller
{
    use DescribesCircles;

    /**
     * How many circles the list shows before paging.
     */
    protected const PER_PAGE = 24;

    /**
     * How recently a circle must have been posted in to count as active.
     */
    protected const ACTIVE_WITHIN_DAYS = 7;

    /**
     * How many people the owner picker offers at once.
     */
    protected const CANDIDATE_LIMIT = 25;

    /**
     * Every circle on the platform, drawn the way a member sees their own.
     *
     * The same card as "My Circles" on purpose: an admin opening this is
     * looking at the same objects, and a second visual language for them would
     * mean learning the product twice. What differs is the scope — all of them,
     * not the ones you happen to belong to — and the owner named on each card,
     * which a member never needs and staff always do.
     *
     * From here the ordinary circle screens do the rest: the policy lets staff
     * through to any circle's members, posts, tracker and manage tabs, so none
     * of those are rebuilt here.
     */
    public function index(Request $request): Response
    {
        $admin = $request->user();
        $since = now()->subDays(self::ACTIVE_WITHIN_DAYS);

        $circles = QueryBuilder::for(Circle::query())
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, mixed $term) => $query
                    ->search(is_string($term) ? $term : null)),
                AllowedFilter::callback('state', function (Builder $query, mixed $state) use ($since): void {
                    $recent = fn (Builder $posts) => $posts->where('posts.created_at', '>=', $since);

                    match ($state) {
                        'active' => $query->whereHas('posts', $recent),
                        'quiet' => $query->whereDoesntHave('posts', $recent),
                        // The circles this screen exists for: nobody inside them
                        // can rename, manage or hand them on.
                        'ownerless' => $query->whereNull('owner_id'),
                        default => null,
                    };
                }),
            )
            ->withCount([
                'posts',
                'posts as recent_posts_count' => fn (Builder $posts) => $posts
                    ->where('posts.created_at', '>=', $since),
            ])
            ->with([
                'owner',
                'members' => fn (Relation $members) => $members
                    ->orderBy('circle_memberships.joined_at')
                    ->limit(self::FACES_ON_CARD),
            ])
            // Ownerless first whatever the sort: they are the ones stuck.
            ->orderByRaw('owner_id is null desc')
            ->defaultSort('name')
            ->allowedSorts('name', 'members_count', 'posts_count', 'created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Circle $circle): array => [
                ...$this->circleListing($admin, $circle),
                'owner' => $circle->owner === null ? null : [
                    'id' => $circle->owner->id,
                    'full_name' => $circle->owner->full_name,
                    'username' => $circle->owner->username,
                    'avatar_url' => $circle->owner->avatar_url,
                ],
            ]);

        return Inertia::render('admin/circles', [
            'circles' => $circles,
            'filter' => $request->string('filter.state')->value() ?: 'all',
            'search' => $request->string('filter.search')->value(),
            'counts' => $this->tabCounts($since),
            'ownerCandidates' => $this->ownerCandidates($request),
            'ownerSearch' => $request->string('owner_search')->value(),
        ]);
    }

    /**
     * Start a circle on somebody else's behalf.
     *
     * Staff make circles for other people, never for themselves — so the owner
     * is asked for rather than assumed to be whoever is signed in, which is the
     * one thing that separates this from the ordinary create form.
     *
     * The writing is {@see CreateCircle}, the same action the app and the web
     * form call, so a circle started here is indistinguishable from any other
     * and its owner lands in it as the first member.
     */
    public function store(StoreCircleRequest $request, CreateCircle $createCircle): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ], [
            'owner_id.required' => 'Choose who will own this circle.',
            'owner_id.exists' => 'That person is no longer around.',
        ]);

        $owner = User::query()->whereKey($validated['owner_id'])->firstOrFail();

        $circle = $createCircle->execute($owner, [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tag' => $request->validated('tag'),
            // Staff choose it on the owner's behalf, the same as the name: a
            // circle opened for somebody should arrive set up the way they
            // asked for it rather than needing a first edit to put right.
            'is_private' => $request->boolean('is_private'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name created, owned by :owner.', [
                'name' => $circle->name,
                'owner' => $owner->full_name,
            ]),
        ]);

        return back();
    }

    /**
     * Who a new circle could be handed to.
     *
     * Searched rather than listed in full: the picker has to work when there
     * are ten thousand accounts, and a select carrying all of them would be
     * both a large payload and unusable.
     *
     * @return list<array<string, mixed>>
     */
    protected function ownerCandidates(Request $request): array
    {
        $search = $request->string('owner_search')->trim()->value();

        return array_values(User::query()
            ->when(filled($search), function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('full_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
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
     * How many circles sit behind each tab.
     *
     * Counted over everything rather than over the filtered list, or every tab
     * would report the number already showing under the open one.
     *
     * @return array<string, int>
     */
    protected function tabCounts(CarbonInterface $since): array
    {
        $all = Circle::query()->count();

        $active = Circle::query()
            ->whereHas('posts', fn (Builder $posts) => $posts
                ->where('posts.created_at', '>=', $since))
            ->count();

        return [
            'all' => $all,
            'active' => $active,
            'quiet' => $all - $active,
            'ownerless' => Circle::query()->whereNull('owner_id')->count(),
        ];
    }
}
