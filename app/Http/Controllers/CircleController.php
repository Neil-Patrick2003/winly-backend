<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTrackerRequest;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMedia;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CircleController extends Controller
{
    /**
     * How many rows a tab shows before paging.
     */
    protected const PER_PAGE = 20;

    /**
     * List the circles the signed-in user belongs to.
     *
     * The landing page for "My Circles": the sidebar holds one link, and the
     * circles themselves are listed here where there is room to say something
     * about each one.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $circles = $user->circleMemberships()
            ->with(['circle' => fn (Relation $query) => $query->withCount('posts')])
            ->join('circles', 'circles.id', '=', 'circle_memberships.circle_id')
            ->orderBy('circles.name')
            ->select('circle_memberships.*')
            ->get()
            ->map(fn (CircleMembership $membership): array => [
                ...$this->circleCard($user, $membership->circle),
                'posts_count' => $membership->circle->posts_count,
                'joined_at' => $membership->joined_at->toIso8601String(),
            ])
            ->all();

        return Inertia::render('circles/index', [
            'circles' => $circles,
        ]);
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
                'media' => $this->media($post->winMeditation->media),
            ];
        }

        if ($post->winLearning !== null) {
            $wins[] = [
                'type' => 'learning',
                'detail' => [
                    'learned_text' => $post->winLearning->learned_text,
                    'reference_source' => $post->winLearning->reference_source,
                ],
                'media' => $this->media($post->winLearning->media),
            ];
        }

        if ($post->winMovement !== null) {
            $wins[] = [
                'type' => 'movement',
                'detail' => [
                    'movement_type' => $post->winMovement->movement_type,
                ],
                'media' => $this->media($post->winMovement->media),
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
     * @param  Collection<int, WinMedia>  $media
     * @return list<array<string, mixed>>
     */
    protected function media(Collection $media): array
    {
        return array_values($media
            ->map(fn (WinMedia $file): array => [
                'id' => $file->id,
                'url' => $file->url,
                'kind' => $file->kind,
            ])
            ->all());
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

        $counts = $this->winCounts(
            $circle,
            array_values($members->getCollection()
                ->map(fn (CircleMembership $membership): string => $membership->user_id)
                ->all()),
            $request->since(),
        );

        return Inertia::render('circles/tracker', [
            'circle' => $this->circleProps($request, $circle),
            'winTypes' => Post::WIN_TYPES,
            'range' => $request->range(),
            'since' => $request->since()?->toDateString(),
            'members' => $members->through(function (CircleMembership $membership) use ($counts): array {
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
                    'total' => array_sum($wins),
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
    protected function winCounts(Circle $circle, array $userIds, ?CarbonInterface $since): array
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
                ->whereExists(fn (QueryBuilder $shared) => $shared
                    ->from('circle_post')
                    ->whereColumn('circle_post.post_id', "{$posts}.id")
                    ->where('circle_post.circle_id', $circle->getKey()))
                ->whereIn("{$posts}.user_id", $userIds)
                ->when($since !== null, fn (Builder $query) => $query
                    ->where("{$table}.completed_at", '>=', $since))
                ->groupBy("{$posts}.user_id")
                ->pluck(DB::raw('count(*)'), "{$posts}.user_id");

            foreach ($rows as $userId => $total) {
                $counts[$userId][$type] = (int) $total;
            }
        }

        return $counts;
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
