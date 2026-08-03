<?php

namespace App\Http\Controllers;

use App\Actions\Circles\CreateCircle;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CircleController extends Controller
{
    /**
     * How many rows a tab shows before paging.
     */
    protected const PER_PAGE = 20;

    /**
     * How recently a circle must have been posted in to count as active.
     */
    protected const ACTIVE_WITHIN_DAYS = 7;

    /**
     * How many faces the stack on a circle's card shows.
     */
    protected const FACES_ON_CARD = 3;

    /**
     * The pastels a circle's card can be washed in.
     *
     * @var list<string>
     */
    protected const WASHES = ['blue', 'lavender', 'pink', 'peach', 'mint', 'butter'];

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
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is yours. You are its first member.', ['name' => $circle->name]),
        ]);

        return to_route('circles.members', $circle);
    }

    /**
     * One circle as its card needs it.
     *
     * @return array<string, mixed>
     */
    protected function circleListing(User $reader, Circle $circle): array
    {
        return [
            ...$this->circleCard($reader, $circle),
            'wash' => $this->washFor($circle->name),
            'posts_count' => $circle->posts_count,
            'is_active' => (int) $circle->getAttribute('recent_posts_count') > 0,
            'faces' => $circle->members
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'avatar_url' => $member->avatar_url,
                ])
                ->values()
                ->all(),
        ];
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
     * The pastel a circle is drawn in, decided by its name.
     *
     * Stable, so a circle keeps its colour across reloads and between the card
     * and the chip, and picked here rather than stored so the palette can be
     * retuned without a migration.
     */
    protected function washFor(string $name): string
    {
        return self::WASHES[abs(crc32(Str::lower($name))) % count(self::WASHES)];
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
                'streak_days' => $membership->user->streak_days,
                'wins_count' => $membership->user->wins_count,
                'joined_at' => $membership->joined_at->toIso8601String(),
                'is_owner' => $membership->user_id === $circle->owner_id,
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
     * Counts only wins shared into this circle. A member's other wins are not
     * the circle's to see — the feed already holds them back — and a tracker
     * that counted them would be reporting on people behind their backs.
     */
    public function tracker(IndexTrackerRequest $request, Circle $circle): Response
    {
        Gate::authorize('view', $circle);

        $members = $circle->memberships()
            ->with('user')
            ->join('users', 'users.id', '=', 'circle_memberships.user_id')
            ->orderBy('users.full_name')
            ->select('circle_memberships.*')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $userIds = array_values($members->getCollection()
            ->map(fn (CircleMembership $membership): string => $membership->user_id)
            ->all());

        $counts = $this->winCounts($circle, $userIds, $request->from(), $request->to());
        $daysLogged = $this->daysLogged($circle, $userIds, $request->from(), $request->to());

        return Inertia::render('circles/tracker', [
            'circle' => $this->circleProps($request, $circle),
            'winTypes' => Post::WIN_TYPES,
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            'days' => $request->days(),
            'members' => $members->through(function (CircleMembership $membership) use ($counts, $daysLogged): array {
                $wins = $counts[$membership->user_id] ?? [];

                return [
                    'id' => $membership->user->id,
                    'full_name' => $membership->user->full_name,
                    'username' => $membership->user->username,
                    'avatar_url' => $membership->user->avatar_url,
                    'streak_days' => $membership->user->streak_days,
                    'longest_streak' => $membership->user->longest_streak,
                    'wins' => collect(Post::WIN_TYPES)
                        ->mapWithKeys(fn (string $type): array => [
                            $type => (int) ($wins[$type] ?? 0),
                        ])
                        ->all(),
                    'total' => $daysLogged[$membership->user_id] ?? 0,
                ];
            }),
        ]);
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
     * @param  list<string>  $userIds
     * @return array<string, array<string, int>> Keyed by user, then win type.
     */
    protected function winCounts(Circle $circle, array $userIds, CarbonInterface $from, CarbonInterface $to): array
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
                    ->where('circle_post.circle_id', $circle->getKey()))
                ->whereIn("{$posts}.user_id", $userIds)
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
     * On how many days each of these people logged extreme self care here.
     *
     * Deliberately not the sum of the win columns: three wins on one Tuesday is
     * still one day of showing up, and the last column of the tracker is about
     * how often somebody turned up rather than how much they stacked into a
     * single day. Counting sums would let one busy afternoon outrank a month of
     * steady practice.
     *
     * Days are read off `completed_at`, the same field the columns filter on,
     * so a sitting done on Sunday and shared on Monday counts for Sunday. The
     * three detail tables cannot be counted together, so each contributes its
     * dates and the union is taken in PHP — a day with a sitting and a run on
     * it must not count twice.
     *
     * @param  list<string>  $userIds
     * @return array<string, int> Keyed by user.
     */
    protected function daysLogged(Circle $circle, array $userIds, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var array<string, array<string, true>> $seen */
        $seen = [];

        $winModels = [new WinMeditation, new WinLearning, new WinMovement];

        foreach ($winModels as $model) {
            $table = $model->getTable();
            $posts = (new Post)->getTable();

            $rows = $model->newQuery()
                ->join($posts, "{$posts}.id", '=', "{$table}.post_id")
                ->whereExists(fn (BaseBuilder $shared) => $shared
                    ->from('circle_post')
                    ->whereColumn('circle_post.post_id', "{$posts}.id")
                    ->where('circle_post.circle_id', $circle->getKey()))
                ->whereIn("{$posts}.user_id", $userIds)
                ->whereBetween("{$table}.completed_at", [$from, $to])
                ->select([
                    "{$posts}.user_id",
                    DB::raw("date({$table}.completed_at) as logged_on"),
                ])
                ->distinct()
                ->toBase()
                ->get();

            foreach ($rows as $row) {
                $seen[$row->user_id][$row->logged_on] = true;
            }
        }

        return array_map(fn (array $days): int => count($days), $seen);
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

    /**
     * A circle described for whoever is reading it.
     *
     * @return array<string, mixed>
     */
    protected function circleCard(User $reader, Circle $circle): array
    {
        return [
            'id' => $circle->id,
            'name' => $circle->name,
            'description' => $circle->description,
            'icon_initial' => $circle->icon_initial,
            'color_hex' => $circle->color_hex,
            'tag' => $circle->tag,
            'members_count' => $circle->members_count,
            'can_manage' => $reader->can('manage', $circle),
        ];
    }
}
