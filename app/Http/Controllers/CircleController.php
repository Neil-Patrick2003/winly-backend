<?php

namespace App\Http\Controllers;

use App\Actions\Circles\CreateCircle;
use App\Concerns\DescribesCircles;
use App\Http\Requests\Api\V1\StoreCircleRequest;
use App\Http\Requests\IndexMyCirclesRequest;
use App\Http\Requests\IndexTrackerRequest;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Rules\MediaFile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CircleController extends Controller
{
    use DescribesCircles;

    /**
     * How many rows a tab shows before paging.
     */
    protected const PER_PAGE = 20;

    /**
     * How recently a circle must have been posted in to count as active.
     */
    protected const ACTIVE_WITHIN_DAYS = 7;

    /**
     * List the circles the signed-in user belongs to.
     *
     * The landing page for "My Circles": the sidebar holds one link, and the
     * circles themselves are listed here where there is room to say something
     * about each one.
     */
    public function index(IndexMyCirclesRequest $request): Response
    {
        $user = $request->user();
        $since = now()->subDays(self::ACTIVE_WITHIN_DAYS);

        /*
         * Built through Spatie's query builder so the filter and the search
         * arrive as declared, whitelisted parameters rather than as hand-rolled
         * reads of the request — and so both are answered in SQL. Filtering the
         * rows in PHP after loading them, as this did, meant the database
         * fetching circles only to throw them away a moment later.
         */
        $circles = QueryBuilder::for($user->circles())
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, mixed $term) => $query
                    ->search(is_string($term) ? $term : null)),
                AllowedFilter::callback(
                    'state',
                    fn (Builder $query, mixed $state) => $this->applyState($query, $state, $since),
                ),
            )
            ->withCount([
                'posts',
                // Whether anything has been shared lately is the one thing a
                // card can say about a circle's health, and it costs a subquery
                // rather than a second round of rows.
                'posts as recent_posts_count' => fn (Builder $posts) => $posts
                    ->where('posts.created_at', '>=', $since),
            ])
            // Three faces for the stack on the card. Limited in the eager load,
            // so a circle of four hundred still costs one query.
            ->with(['members' => fn (Relation $members) => $members
                ->orderBy('circle_memberships.joined_at')
                ->limit(self::FACES_ON_CARD)])
            // One query for the whole page rather than one per card.
            ->with('parent')
            ->defaultSort('name')
            ->allowedSorts('name', 'members_count', 'posts_count')
            ->get()
            ->map(fn (Circle $circle): array => $this->circleListing($user, $circle))
            ->all();

        return Inertia::render('circles/index', [
            'circles' => $circles,
            'filter' => $request->filter(),
            'search' => $request->search(),
            'counts' => $this->tabCounts($user, $since),
        ]);
    }

    /**
     * Narrow circles to those that have been posted in lately, or those that
     * have not.
     *
     * @param  Builder<Circle>  $query
     */
    protected function applyState(Builder $query, mixed $state, CarbonInterface $since): void
    {
        $recent = fn (Builder $posts) => $posts->where('posts.created_at', '>=', $since);

        match ($state) {
            'active' => $query->whereHas('posts', $recent),
            'quiet' => $query->whereDoesntHave('posts', $recent),
            default => null,
        };
    }

    /**
     * Start a circle from the web.
     *
     * The same rules and the same writing as the app's own endpoint, so a
     * circle made here is indistinguishable from one made on a phone.
     */
    public function store(StoreCircleRequest $request, CreateCircle $createCircle): RedirectResponse
    {
        $circle = $createCircle->execute($request->user(), [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'tag' => $request->validated('tag'),
            // Absent is public, as it is on the app's endpoint. `boolean()`
            // reads the "0"/"1" a checkbox posts the same way it reads a
            // JSON `true`, which is what lets one rule serve both.
            'is_private' => $request->boolean('is_private'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is yours. You are its first member.', ['name' => $circle->name]),
        ]);

        return to_route('circles.members', $circle);
    }

    /**
     * How many circles sit behind each tab.
     *
     * Counted over everything the reader belongs to rather than over the
     * filtered list, or every tab would report the number showing under the
     * tab already open.
     *
     * @return array<string, int>
     */
    protected function tabCounts(User $user, CarbonInterface $since): array
    {
        $active = $user->circles()
            ->whereHas('posts', fn (Builder $posts) => $posts
                ->where('posts.created_at', '>=', $since))
            ->count();

        $all = $user->circles()->count();

        return ['all' => $all, 'active' => $active, 'quiet' => $all - $active];
    }

    /**
     * Show who is in the circle.
     */
    public function members(Request $request, Circle $circle): Response
    {
        Gate::authorize('view', $circle);

        $members = $circle->memberships()
            ->with('user')
            ->orderBy('joined_at')
            ->paginate(self::PER_PAGE)
            ->through(fn (CircleMembership $membership): array => [
                'id' => $membership->user->id,
                'full_name' => $membership->user->full_name,
                'username' => $membership->user->username,
                'avatar_url' => $membership->user->avatar_url,
                // The run still standing rather than the stored column, for the
                // same reason the tracker asks this way.
                'streak_days' => $membership->user->currentStreak(),
                'wins_count' => $membership->user->wins_count,
                'joined_at' => $membership->joined_at->toIso8601String(),
                'is_owner' => $membership->user_id === $circle->owner_id,
                // Runs the circle without having made it. Marked apart from the
                // founder, because the list is telling the group who to go to
                // rather than who started it.
                'is_co_owner' => $membership->role === CircleMembership::ROLE_OWNER
                    && $membership->user_id !== $circle->owner_id,
            ]);

        return Inertia::render('circles/members', [
            'circle' => $this->circleProps($request, $circle),
            'members' => $members,
        ]);
    }

    /**
     * How many comments a post carries inline before the rest are left to the
     * count.
     */
    protected const COMMENT_PREVIEW = 3;

    /**
     * Show the wins shared into the circle, in full.
     *
     * Each post arrives with its text, its files, whether the reader has liked
     * it and the last few comments — everything the card draws, so opening the
     * tab is one round trip rather than one per post.
     */
    public function posts(Request $request, Circle $circle): Response
    {
        Gate::authorize('view', $circle);

        $viewer = $request->user();

        $posts = $circle->posts()
            /*
             * Being allowed through the door is not being allowed to read
             * everything behind it.
             *
             * A public circle admits anybody signed in, member or not — that is
             * what the gate above means. But a win on this wall was addressed
             * to the circle's *members*, and `all_circles` is a different
             * answer from `public`. Without this a stranger could read a whole
             * group's wins by opening its URL, having joined nothing.
             *
             * The same filter the API's circle wall has always applied. The two
             * read the same rows now, which is what stops one of them being the
             * way round the other.
             */
            ->visibleTo($viewer)
            ->with([
                'user',
                'winMeditation.media',
                'winLearning.media',
                'winMovement.media',
                'viewerLike' => fn (Relation $query) => $query->whereBelongsTo($viewer),
                'comments' => fn (Relation $query) => $query
                    ->with('user')
                    ->latestFirst()
                    ->limit(self::COMMENT_PREVIEW),
            ])
            ->latestFirst()
            ->paginate(self::PER_PAGE)
            ->through(fn (Post $post): array => [
                'id' => $post->id,
                'caption' => $post->caption,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'viewer_has_liked' => $post->viewerLike !== null,
                'created_at' => $post->created_at?->toIso8601String(),
                'author' => $this->author($post->user),
                'wins' => $this->wins($post),
                'comments' => $post->comments
                    ->map(fn (Comment $comment): array => [
                        'id' => $comment->id,
                        'text' => $comment->text,
                        'created_at' => $comment->created_at?->toIso8601String(),
                        'author' => $this->author($comment->user),
                        'can_delete' => $viewer->can('delete', $comment),
                    ])
                    ->all(),
            ]);

        return Inertia::render('circles/posts', [
            'circle' => $this->circleProps($request, $circle),
            'posts' => $posts,
        ]);
    }

    /**
     * Every win on a post, with the detail and files that belong to it.
     *
     * @return list<array<string, mixed>>
     */
    protected function wins(Post $post): array
    {
        $wins = [];

        if ($post->winMeditation !== null) {
            $wins[] = [
                'type' => 'meditation',
                'detail' => [
                    'duration_minutes' => $post->winMeditation->duration_minutes,
                    'completed' => $post->winMeditation->completed,
                ],
                'media' => $this->media($post->winMeditation->winMedia()),
            ];
        }

        if ($post->winLearning !== null) {
            $wins[] = [
                'type' => 'learning',
                'detail' => [
                    'learned_text' => $post->winLearning->learned_text,
                    'reference_source' => $post->winLearning->reference_source,
                ],
                'media' => $this->media($post->winLearning->winMedia()),
            ];
        }

        if ($post->winMovement !== null) {
            $wins[] = [
                'type' => 'movement',
                'detail' => [
                    'movement_type' => $post->winMovement->movement_type,
                ],
                'media' => $this->media($post->winMovement->winMedia()),
            ];
        }

        return $wins;
    }

    /**
     * The photos and clips on one win, in the order they were uploaded.
     *
     * Takes the loaded files rather than the win they hang off: the three win
     * models share their media through a concern, and a trait is not something
     * a parameter can be typed against.
     *
     * @param  MediaCollection<int, Media>  $media
     * @return list<array<string, mixed>>
     */
    protected function media(MediaCollection $media): array
    {
        $files = [];

        foreach ($media as $file) {
            $files[] = [
                'id' => $file->uuid,
                // Absolute, for the same reason the API's own payload is.
                'url' => url($file->getUrl()),
                'kind' => MediaFile::kindForMime($file->mime_type),
            ];
        }

        return $files;
    }

    /**
     * A person as they appear beside their own words.
     *
     * @return array<string, mixed>
     */
    protected function author(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
        ];
    }

    /**
     * Show what each member has been winning at, and for how long a run.
     *
     * Counts only wins shared into these circles. A member's other wins are not
     * the circle's to see — the feed already holds them back — and a tracker
     * that counted them would be reporting on people behind their backs.
     *
     * A circle with circles inside it counts them too, and the people in them.
     * The tab is the group's picture of itself, and a picture that stopped at
     * the outer wall would leave out most of what the group has been doing.
     * The picker narrows it back down where somebody wants one of them alone.
     */
    public function tracker(IndexTrackerRequest $request, Circle $circle): Response
    {
        Gate::authorize('view', $circle);

        // This circle and the ones inside it, in the order the picker shows.
        $available = collect([$circle])->concat(
            $circle->isSubCircle() ? [] : $circle->subCircles()->orderBy('name')->get()
        );

        /*
         * What the reader asked for, narrowed to what is actually on offer.
         *
         * Intersected rather than trusted: the ids arrive in the query string,
         * and one naming somebody else's circle would otherwise count wins this
         * circle has no business seeing. An empty choice reads as "all", which
         * is what an untouched picker means.
         */
        $offered = array_values(array_map(strval(...), $available->pluck('id')->all()));
        $chosen = array_values(array_intersect($request->circleIds(), $offered));
        $counting = $chosen === [] ? $offered : $chosen;

        /*
         * The people in any of those circles, listed once each.
         *
         * Paginated over users rather than over membership rows, which is what
         * the tab is actually a list of: somebody in the parent and two circles
         * inside it has three memberships and belongs on one line. Grouping the
         * membership rows said the same thing but asked MySQL to hand back
         * columns it had not grouped by — `only_full_group_by` refuses that,
         * and the subquery a paginator wraps the count in refuses it first.
         */
        $search = $request->search();
        $filters = $request->filters();
        $sort = $request->sort();

        /*
         * Everybody this tab could list, in name order, before a number is
         * weighed against any of them.
         *
         * Ids and the two streak columns rather than whole rows: the twenty on
         * the page are fetched in full further down, and everyone else is here
         * only to be counted, filtered and put in order. The list has to be
         * whole for that — filtering or reordering twenty rows already chosen
         * alphabetically would answer a different question on every page, and
         * hand back a page count that still counted everybody.
         */
        $candidates = User::query()
            ->whereIn('id', CircleMembership::query()
                ->whereIn('circle_id', $counting)
                ->select('user_id'))
            /*
             * Name or username, because either is what somebody looking for a
             * particular member has to hand. Wildcards in the term are escaped
             * rather than honoured — a `%` typed into the box is a character
             * somebody is looking for, not a licence to match everybody.
             */
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('full_name', 'like', $term)
                    ->orWhere('username', 'like', $term));
            })
            ->orderBy('full_name')
            // The id breaks ties, so a page boundary does not shuffle two
            // people who share a name.
            ->orderBy('id')
            ->get(['id', 'full_name', 'last_win_on', 'streak_days']);

        /*
         * Everyone's days and everyone's wins, but only where something asks.
         *
         * The ordinary tab asks nothing of either beyond the twenty it is about
         * to show, and gathering the whole circle for it would be work nobody
         * reads. A filter or an order that runs on the numbers does ask, and
         * has to be answered for everybody at once.
         */
        $everyoneLogged = $request->weighsEveryone()
            ? $this->loggedDays($counting, null, $request->from(), $request->to())
            : null;

        $everyoneCounts = $request->ranksByKind()
            ? $this->winCounts($counting, null, $request->from(), $request->to())
            : null;

        $awarded = $everyoneLogged === null
            ? null
            : $this->awardedMembers($request, $counting, $everyoneLogged);

        $ordered = $this->rankMembers(
            $candidates,
            $filters,
            $sort,
            $everyoneLogged,
            $everyoneCounts,
            $awarded
        );

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pageIds = array_slice($ordered, ($currentPage - 1) * self::PER_PAGE, self::PER_PAGE);

        $onPage = User::query()->whereIn('id', $pageIds)->get()->keyBy('id');

        /*
         * The page's numbers, taken from what was already gathered where the
         * whole circle had to be weighed anyway.
         */
        $pageKeys = array_flip($pageIds);

        $counts = $everyoneCounts !== null
            ? array_intersect_key($everyoneCounts, $pageKeys)
            : $this->winCounts($counting, $pageIds, $request->from(), $request->to());

        // One gathering of the days behind both of the last two columns.
        $logged = $everyoneLogged !== null
            ? array_intersect_key($everyoneLogged, $pageKeys)
            : $this->loggedDays($counting, $pageIds, $request->from(), $request->to());

        $daysLogged = $this->daysLogged($logged);
        $pointsScored = $this->pointsScored($logged);

        $rows = [];

        foreach ($pageIds as $id) {
            $member = $onPage->get($id);

            if ($member === null) {
                continue;
            }

            $wins = $counts[$id] ?? [];

            $rows[] = [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'username' => $member->username,
                'avatar_url' => $member->avatar_url,
                /*
                 * The streak still standing, not the stored column.
                 *
                 * `streak_days` is the run ending at the member's last win,
                 * whenever that was — it keeps its number long after the run is
                 * over, which is how somebody with an empty row ends up wearing
                 * a one day streak. `currentStreak()` is what decides it has
                 * lapsed, and it is what the profile and the API already answer
                 * with.
                 */
                'streak_days' => $member->currentStreak(),
                'longest_streak' => $member->longest_streak,
                'wins' => collect(Post::WIN_TYPES)
                    ->mapWithKeys(fn (string $type): array => [
                        $type => (int) ($wins[$type] ?? 0),
                    ])
                    ->all(),
                'total' => $daysLogged[$id] ?? 0,
                /*
                 * A point per kind per day, so a day with all three kinds on it
                 * is worth three and a second sitting that evening is worth
                 * nothing further.
                 */
                'total_points' => $pointsScored[$id] ?? 0,
            ];
        }

        /*
         * Built by hand rather than by `paginate()`, because the order and the
         * total are settled in PHP: points and days are counted off dates the
         * database cannot group the way the columns read them, and the streak
         * is a lapse rule rather than a column.
         */
        $members = (new LengthAwarePaginator(
            $rows,
            count($ordered),
            self::PER_PAGE,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        ))->withQueryString();

        return Inertia::render('circles/tracker', [
            /*
             * The circles the picker offers, and which are counted right now.
             * A circle with none inside it sends a single entry, and the page
             * leaves the control out rather than showing a choice of one.
             */
            'circleOptions' => $available
                ->map(fn (Circle $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'is_parent' => $option->id === $circle->id,
                ])
                ->all(),
            'selectedCircles' => $counting,
            'search' => $search,
            'filters' => $filters,
            'sort' => $sort,
            'circle' => $this->circleProps($request, $circle),
            'winTypes' => Post::WIN_TYPES,
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            'days' => $request->days(),
            'members' => $members,
        ]);
    }

    /**
     * Who the tab lists, in the order it lists them.
     *
     * Every filter here narrows the rows rather than the counting: a member who
     * survives one is shown the same numbers the unfiltered tab would have
     * shown them. They are applied together and each has to pass, so a panel
     * with several boxes ticked reads as one question rather than several.
     *
     * Ordering is settled here too, on figures the database cannot sort by:
     * points and days are counted off dates it cannot group the way the columns
     * read them, and the streak is a lapse rule rather than the stored column.
     * The sort is stable and the candidates arrive alphabetically, so people
     * level on a number stay in name order rather than shuffling between loads.
     *
     * @param  Collection<int, User>  $candidates  In name order.
     * @param  array{
     *     completion: array{with_reference: bool, complete: bool, exclude_referenced: bool},
     *     activity: list<string>,
     *     kinds: list<string>,
     *     min_points: int|null,
     *     min_days: int|null,
     *     streaking: bool,
     *     min_streak: int|null,
     * }  $filters
     * @param  array{by: string, direction: string}  $sort
     * @param  array<string, array<string, array<string, true>>>|null  $logged
     * @param  array<string, array<string, int>>|null  $counts
     * @param  list<string>|null  $awarded  Null where no award was asked for.
     * @return list<string> Member ids, in the order the table shows them.
     */
    protected function rankMembers(
        Collection $candidates,
        array $filters,
        array $sort,
        ?array $logged,
        ?array $counts,
        ?array $awarded,
    ): array {
        $daysLogged = $logged === null ? [] : $this->daysLogged($logged);
        $pointsScored = $logged === null ? [] : $this->pointsScored($logged);

        // A lookup rather than a scan, so a circle of hundreds is not searched
        // once per member.
        $awardedKeys = $awarded === null ? null : array_flip($awarded);

        $ranked = [];

        foreach ($candidates as $member) {
            $id = $member->id;
            $days = $daysLogged[$id] ?? 0;
            $points = $pointsScored[$id] ?? 0;
            $streak = $member->currentStreak();

            if ($awardedKeys !== null && ! isset($awardedKeys[$id])) {
                continue;
            }

            /*
             * Turned up, or did not. Both boxes ticked is everybody, which is
             * what neither of them already says — so it takes exactly one of
             * them to narrow anything.
             */
            if (count($filters['activity']) === 1
                && ($filters['activity'][0] === 'active') !== ($days > 0)) {
                continue;
            }

            // Every kind ticked rather than any of them: the box asks what a
            // practice looks like, and somebody doing one of two is not doing
            // both.
            foreach ($filters['kinds'] as $kind) {
                if (($logged[$id][$kind] ?? []) === []) {
                    continue 2;
                }
            }

            if ($filters['min_points'] !== null && $points < $filters['min_points']) {
                continue;
            }

            if ($filters['min_days'] !== null && $days < $filters['min_days']) {
                continue;
            }

            // A run that has lapsed is not a run, which is what
            // `currentStreak()` already decided — the stored column would say
            // otherwise long after it ended.
            if ($filters['streaking'] && $streak === 0) {
                continue;
            }

            if ($filters['min_streak'] !== null && $streak < $filters['min_streak']) {
                continue;
            }

            $ranked[] = [
                'id' => $id,
                'streak' => $streak,
                'days' => $days,
                'points' => $points,
                // The win column being ordered by, where one is. Counting wins
                // rather than days, which is what that column shows.
                'kind' => (int) ($counts[$id][$sort['by']] ?? 0),
            ];
        }

        if ($sort['by'] === 'name') {
            $names = array_column($ranked, 'id');

            return $sort['direction'] === 'desc' ? array_reverse($names) : $names;
        }

        $on = in_array($sort['by'], Post::WIN_TYPES, true) ? 'kind' : $sort['by'];
        $rising = $sort['direction'] === 'asc' ? 1 : -1;

        usort($ranked, fn (array $a, array $b): int => $rising * ($a[$on] <=> $b[$on]));

        return array_column($ranked, 'id');
    }

    /**
     * Who the award filters leave standing, or null where none were ticked.
     *
     * Two awards, and the boxes are a union rather than a narrowing: ticking
     * both asks for everybody who finished, cited or not. `exclude_referenced`
     * is the one exception — it takes the cited finishers back out of the
     * plain award, so a circle handing out two prizes can list the people each
     * one is for without the same names appearing under both.
     *
     * @param  list<string>  $circleIds  This circle and any counted with it.
     * @param  array<string, array<string, array<string, true>>>  $logged  Every
     *                                                                     member's days, gathered already.
     * @return list<string>|null Null where the tab is unfiltered.
     */
    protected function awardedMembers(IndexTrackerRequest $request, array $circleIds, array $logged): ?array
    {
        $wanted = $request->completionFilters();

        if (! $wanted['with_reference'] && ! $wanted['complete']) {
            return null;
        }

        $runs = $this->completeRuns($logged, $circleIds, $request->from(), $request->to(), $request->days());

        $awarded = $wanted['with_reference'] ? $runs['with_reference'] : [];

        if ($wanted['complete']) {
            $awarded = array_merge($awarded, $wanted['exclude_referenced']
                ? array_diff($runs['complete'], $runs['with_reference'])
                : $runs['complete']);
        }

        return array_values(array_unique($awarded));
    }

    /**
     * Who logged every kind on every day of the range, and who cited as well.
     *
     * A complete run is the whole range with nothing missed: all three kinds
     * on each of its days. Days rather than wins is what the points column
     * already counts — a second sitting on a Tuesday already covered adds
     * nothing, and cannot stand in for the Wednesday nobody logged. So the
     * days gathered for the columns answer this too.
     *
     * The cited award asks more of the learning — every learning win in the
     * range carries a source, not merely one of them — so it is the finishers
     * less anyone who left a source out.
     *
     * @param  array<string, array<string, array<string, true>>>  $logged  Every
     *                                                                     member's days, keyed by user then kind.
     * @param  list<string>  $circleIds  This circle and any counted with it.
     * @param  int  $days  How many days the range covers, both ends included.
     * @return array{complete: list<string>, with_reference: list<string>}
     */
    protected function completeRuns(array $logged, array $circleIds, CarbonInterface $from, CarbonInterface $to, int $days): array
    {
        $complete = [];

        foreach ($logged as $userId => $byType) {
            foreach (Post::WIN_TYPES as $type) {
                if (count($byType[$type] ?? []) !== $days) {
                    continue 2;
                }
            }

            $complete[] = (string) $userId;
        }

        return [
            'complete' => $complete,
            'with_reference' => array_values(array_diff(
                $complete,
                $this->uncitedLearners($circleIds, $from, $to)
            )),
        ];
    }

    /**
     * Who left a source out of a learning win here, even once.
     *
     * A blank string counts as nothing cited, whatever the column holds: a box
     * submitted empty is not a reference, and storing it as `''` rather than
     * null is a detail of the form rather than a claim about the learning.
     *
     * @param  list<string>  $circleIds  This circle and any counted with it.
     * @return list<string>
     */
    protected function uncitedLearners(array $circleIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $table = (new WinLearning)->getTable();
        $posts = (new Post)->getTable();

        $rows = WinLearning::query()
            ->join($posts, "{$posts}.id", '=', "{$table}.post_id")
            ->whereExists(fn (BaseBuilder $shared) => $shared
                ->from('circle_post')
                ->whereColumn('circle_post.post_id', "{$posts}.id")
                ->whereIn('circle_post.circle_id', $circleIds))
            ->whereBetween("{$table}.completed_at", [$from, $to])
            ->where(fn (Builder $blank) => $blank
                ->whereNull("{$table}.reference_source")
                ->orWhere("{$table}.reference_source", ''))
            ->select("{$posts}.user_id")
            ->distinct()
            ->toBase()
            ->get();

        $learners = [];

        foreach ($rows as $row) {
            $learners[] = (string) $row->user_id;
        }

        return $learners;
    }

    /**
     * How many wins of each kind these people shared into this circle.
     *
     * One grouped query per kind rather than one per member: the three detail
     * tables cannot be counted together, but each of them can be counted for
     * everybody on the page at once.
     *
     * The date filter reads `completed_at` rather than when the post was
     * written — a sitting done on Sunday and shared on Monday belongs to
     * Sunday.
     *
     * @param  list<string>  $circleIds  This circle and any counted with it.
     * @param  list<string>|null  $userIds  Null counts for everyone who posted
     *                                      here, which is what ordering the
     *                                      whole tab by a win column needs.
     * @return array<string, array<string, int>> Keyed by user, then win type.
     */
    protected function winCounts(array $circleIds, ?array $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($userIds === []) {
            return [];
        }

        $counts = [];

        $winModels = [
            'meditation' => new WinMeditation,
            'learning' => new WinLearning,
            'movement' => new WinMovement,
        ];

        foreach ($winModels as $type => $model) {
            $table = $model->getTable();
            $posts = (new Post)->getTable();

            $rows = $model->newQuery()
                ->join($posts, "{$posts}.id", '=', "{$table}.post_id")
                // A post reaches a circle through the pivot now. An existence
                // check rather than a second join, so a win shared into several
                // circles is still counted once here.
                ->whereExists(fn (BaseBuilder $shared) => $shared
                    ->from('circle_post')
                    ->whereColumn('circle_post.post_id', "{$posts}.id")
                    ->whereIn('circle_post.circle_id', $circleIds))
                ->when($userIds !== null, fn (Builder $page) => $page
                    ->whereIn("{$posts}.user_id", $userIds))
                ->whereBetween("{$table}.completed_at", [$from, $to])
                ->groupBy("{$posts}.user_id")
                ->pluck(DB::raw('count(*)'), "{$posts}.user_id");

            foreach ($rows as $userId => $total) {
                $counts[$userId][$type] = (int) $total;
            }
        }

        return $counts;
    }

    /**
     * Which days each of these people logged each kind of win here.
     *
     * The raw material behind both of the tracker's last two columns, gathered
     * once. Days are read off `completed_at`, the same field the columns filter
     * on, so a sitting done on Sunday and shared on Monday counts for Sunday.
     * The three detail tables cannot be counted together, so each contributes
     * its own dates and they are folded together in PHP.
     *
     * Dates rather than counts, because both columns are about days somebody
     * turned up rather than posts they filed: a second sitting on a Tuesday
     * already covered lands on a key that is already there.
     *
     * @param  list<string>  $circleIds  This circle and any counted with it.
     * @param  list<string>|null  $userIds  Null weighs everyone who posted
     *                                      here, which is what deciding an
     *                                      award needs — it settles who
     *                                      reaches a page, so it cannot be
     *                                      asked of one page's people.
     * @return array<string, array<string, array<string, true>>> Keyed by user,
     *                                                           then win type,
     *                                                           then date.
     */
    protected function loggedDays(array $circleIds, ?array $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var array<string, array<string, array<string, true>>> $seen */
        $seen = [];

        $winModels = [
            'meditation' => new WinMeditation,
            'learning' => new WinLearning,
            'movement' => new WinMovement,
        ];

        foreach ($winModels as $type => $model) {
            $table = $model->getTable();
            $posts = (new Post)->getTable();

            $rows = $model->newQuery()
                ->join($posts, "{$posts}.id", '=', "{$table}.post_id")
                ->whereExists(fn (BaseBuilder $shared) => $shared
                    ->from('circle_post')
                    ->whereColumn('circle_post.post_id', "{$posts}.id")
                    ->whereIn('circle_post.circle_id', $circleIds))
                ->when($userIds !== null, fn (Builder $page) => $page
                    ->whereIn("{$posts}.user_id", $userIds))
                ->whereBetween("{$table}.completed_at", [$from, $to])
                ->select([
                    "{$posts}.user_id",
                    DB::raw("date({$table}.completed_at) as logged_on"),
                ])
                ->distinct()
                ->toBase()
                ->get();

            foreach ($rows as $row) {
                $seen[$row->user_id][$type][$row->logged_on] = true;
            }
        }

        return $seen;
    }

    /**
     * On how many days each of these people logged extreme self care here.
     *
     * Deliberately not the sum of the win columns: three wins on one Tuesday is
     * still one day of showing up, and the column is about how often somebody
     * turned up rather than how much they stacked into a single day. Counting
     * sums would let one busy afternoon outrank a month of steady practice.
     *
     * The kinds are unioned rather than added, so a day carrying both a sitting
     * and a run counts once.
     *
     * @param  array<string, array<string, array<string, true>>>  $logged
     * @return array<string, int> Keyed by user.
     */
    protected function daysLogged(array $logged): array
    {
        return array_map(function (array $byType): int {
            $days = [];

            foreach ($byType as $dates) {
                $days += $dates;
            }

            return count($days);
        }, $logged);
    }

    /**
     * What each of these people scored here.
     *
     * A kind is worth a point on a day it was logged, however many times it was
     * logged that day — so a full day of all three kinds scores three, and a
     * fourth sitting that same evening scores nothing further. Adding the raw
     * wins instead would pay somebody for posting the same practice twice, and
     * counting whole days instead would say nothing the days column has not
     * already said.
     *
     * @param  array<string, array<string, array<string, true>>>  $logged
     * @return array<string, int> Keyed by user.
     */
    protected function pointsScored(array $logged): array
    {
        return array_map(
            fn (array $byType): int => array_sum(array_map(count(...), $byType)),
            $logged
        );
    }

    /**
     * The circle header every tab shares.
     *
     * @return array<string, mixed>
     */
    protected function circleProps(Request $request, Circle $circle): array
    {
        return $this->circleCard($request->user(), $circle);
    }
}
