<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecalculateWinStats;
use App\Actions\RecordWinStreak;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPostRequest;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Http\Requests\Api\V1\UpdatePostRequest;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Support\Day;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PostController extends Controller
{
    /**
     * The author and win detail carried alongside every post row.
     *
     * @var list<string>
     */
    protected const WIN_RELATIONS = [
        'winMeditation.media',
        'winLearning.media',
        'winMovement.media',
    ];

    /**
     * The relations to load for a post being read by the given user.
     *
     * The viewer's own like is constrained to them, so a feed of any
     * length still costs one extra query rather than one per post.
     *
     * The author's followers are narrowed the same way, to the viewer alone:
     * the only question the feed asks is whether *this* reader follows them,
     * and loading the real follower list to answer it would drag every
     * follower of every author into memory.
     *
     * @return array<int|string, string|Closure(Relation<*, *, *>): mixed>
     */
    protected function relationsFor(User $viewer): array
    {
        return [
            ...self::WIN_RELATIONS,
            'viewerLike' => fn (Relation $query) => $query->whereBelongsTo($viewer),
            // Narrowed to the reader for the same reason, and carried on every
            // post so a card knows whether its bookmark is already filled in.
            'viewerSave' => fn (Relation $query) => $query->whereBelongsTo($viewer),
            'user' => fn (Relation $query) => $query->withActiveStory(),
            'user.followers' => fn (Relation $query) => $query->whereKey($viewer->getKey()),
            // One eager load for the whole page, so a feed of circle posts
            // costs one query rather than one per post. The parent rides along
            // so a chip can name a circle the way it is named everywhere else.
            'circles.parent',
        ];
    }

    /**
     * The posts shared into one circle, newest first.
     *
     * A circle's own wall. Cursor paginated for the same reason the feed is:
     * it grows from the top, and an offset page would repeat or skip posts as
     * new ones land between one request and the next.
     *
     * @return AnonymousResourceCollection<int, PostResource>
     */
    public function circle(IndexPostRequest $request, Circle $circle): AnonymousResourceCollection
    {
        Gate::authorize('view', $circle);

        $posts = $circle->posts()
            // The circle gate above has already established the reader is a
            // member, so everything placed here is theirs to read. Applied
            // anyway: this is the one list where the narrowing is implied by
            // another check rather than stated, and an implied boundary is the
            // kind that goes missing when the other check is later relaxed.
            ->visibleTo($request->user())
            ->with($this->relationsFor($request->user()))
            ->latestFirst()
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * One person's own posts, newest first.
     *
     * What a profile shows — and only the part of it this reader is allowed to
     * see. A circle post is listed for its members and for nobody else, so two
     * people opening the same profile get different lists, which is the whole
     * point of the setting.
     *
     * @return AnonymousResourceCollection<int, PostResource>
     */
    public function byUser(IndexPostRequest $request, User $user): AnonymousResourceCollection
    {
        $posts = $user->posts()
            ->visibleTo($request->user())
            ->with($this->relationsFor($request->user()))
            ->latestFirst()
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * The posts this person has kept, most recently saved first.
     *
     * Ordered by when it was saved and not by when it was written: the list is
     * a pile you put things on, so the last thing added is on top even if the
     * post itself is a month old.
     *
     * The posts are queried through a join rather than by paginating the saves
     * and swapping the posts in afterwards — the cursor has to describe the
     * rows the paginator actually holds, or the next page is built against the
     * wrong table.
     *
     * @return AnonymousResourceCollection<int, PostResource>
     */
    public function saved(IndexPostRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        $posts = Post::query()
            ->join('saved_posts', 'saved_posts.post_id', '=', 'posts.id')
            ->where('saved_posts.user_id', $viewer->getKey())
            /*
             * Kept once and readable now are two different questions. Somebody
             * may save a circle post and later leave that circle, and the pile
             * is not a way around the boundary — what they can no longer see
             * they can no longer see, saved or not.
             */
            ->visibleTo($viewer)
            ->select('posts.*')
            // Aliased onto the model so the cursor can read what it orders by.
            // The save's id breaks ties on its timestamp.
            ->addSelect([
                'saved_posts.created_at as saved_at',
                'saved_posts.id as save_id',
            ])
            ->with($this->relationsFor($viewer))
            ->orderByDesc('saved_at')
            ->orderByDesc('save_id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * Show one post in full.
     *
     * Comments are not carried here. `comments_count` says how many there are,
     * and the paginated comment list serves them: a thread has no upper bound,
     * and a post view that grew with it would eventually be unusable.
     */
    public function show(Request $request, Post $post): PostResource
    {
        Gate::authorize('view', $post);

        $post->load($this->relationsFor($request->user()));

        return new PostResource($post);
    }

    /**
     * List the feed, newest first.
     *
     * Cursor paginated rather than page numbered: the feed grows from the top,
     * and an offset page would repeat or skip posts as new wins land between
     * one request and the next.
     *
     * @return AnonymousResourceCollection<int, PostResource>
     */
    public function index(IndexPostRequest $request): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $feed = $request->feed();

        $posts = Post::query()
            ->with($this->relationsFor($viewer))
            /*
             * What the reader may see, before anything about how they asked to
             * see it. This is the boundary; the two below are only routes to
             * it, and a post excluded here is excluded from every one of them.
             */
            ->visibleTo($viewer)
            /*
             * Narrowed by how the reader wants to arrive at the posts rather
             * than by who may see them — the line above has already settled
             * that. The default feed is everything they are allowed to read;
             * these two are ways of asking for less.
             *
             * Both narrowed feeds are subqueries against the reader's own
             * relations, so neither loads a list of ids to ask a question the
             * database can answer, and neither disturbs the cursor's ordering.
             */
            ->when(
                $feed === 'following',
                fn (Builder $query) => $query->followedBy($viewer)
            )
            ->when(
                $feed === 'circles',
                fn (Builder $query) => $query->inCirclesOf($viewer)
            )
            ->latestFirst()
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * Record a post and the wins it carries.
     *
     * The post row, every win detail and their files are written together, so
     * a rejected detail can never leave a captionless post stranded in the
     * feed.
     *
     * The streak moves with the post rather than with the wins on it: posting
     * three wins at once is still one day shown up for. The total moves with
     * the wins, but only once per pillar per day — see {@see firstOfTheirDay}.
     */
    public function store(StorePostRequest $request, RecordWinStreak $streak): JsonResponse
    {
        $post = DB::transaction(function () use ($request, $streak): Post {
            $user = $request->user();

            $visibility = (string) $request->validated('visibility');

            $post = $user->posts()->create($request->safe()->only(['caption', 'visibility']));

            /*
             * One post, attached to every circle it was shared with. Attaching
             * rather than duplicating is what keeps a win shared with ten
             * circles one thing — one comment thread, one set of likes, and one
             * row in anybody's feed.
             */
            $post->circles()->sync($this->circlesFor(
                $user,
                $visibility,
                $request->validated('circle_ids') ?? [],
            ));

            $wins = $request->validated('wins');

            /*
             * Asked before the rows are written, so that a win being recorded
             * right now is never mistaken for the earlier one that beat it.
             */
            $counting = $this->firstOfTheirDay($user, $wins);

            foreach ($wins as $win) {
                $this->recordWin($post, $win);
            }

            $user->increment('wins_count', $counting);

            $streak->execute($user);

            return $post;
        });

        $post->load($this->relationsFor($request->user()));

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Rewrite a post.
     *
     * The request describes what the post should end up being rather than what
     * changed about it, so `wins` is the full list: a kind present is created
     * or updated, and a kind left out is removed along with its media. That is
     * simpler to be sure of than a patch, and it matches an edit screen, which
     * submits the whole form back.
     *
     * The totals are rebuilt afterwards instead of nudged, because an edit can
     * move a win to a different day, and a streak cannot be adjusted by a delta
     * once the day it was counted on has changed.
     */
    public function update(UpdatePostRequest $request, Post $post, RecalculateWinStats $stats): PostResource
    {
        Gate::authorize('update', $post);

        /*
         * Files are deleted after the transaction commits, never inside it.
         *
         * A rollback puts the rows back; nothing puts the bytes back. So the
         * media is collected while its rows go, and only unlinked once the
         * database has agreed to the whole edit.
         */
        $discarded = [];

        DB::transaction(function () use ($request, $post, &$discarded): void {
            $visibility = (string) $request->validated('visibility');

            $post->update($request->safe()->only(['caption', 'visibility']));

            /*
             * Re-resolved from the sharing setting every time, because the two
             * are one answer: the circles a post sits in are what `visibility`
             * means, and leaving them where they were while the setting moved
             * would produce a public post still pinned inside a circle, or a
             * circle post reaching nobody.
             *
             * The author is the reader for this, not whoever is editing —
             * the policy has already established those are the same person.
             */
            $post->circles()->sync($this->circlesFor(
                $post->user,
                $visibility,
                $request->validated('circle_ids') ?? [],
            ));

            $wins = $request->validated('wins');
            $keeping = array_column($wins, 'type');

            foreach (Post::WIN_TYPES as $type) {
                if (! in_array($type, $keeping, true)) {
                    $discarded = [...$discarded, ...$this->removeWin($post, $type)];
                }
            }

            foreach ($wins as $win) {
                $discarded = [...$discarded, ...$this->applyWin($post, $win)];
            }
        });

        $this->discardFiles($discarded);

        $stats->execute($request->user());

        return new PostResource($post->fresh($this->relationsFor($request->user())));
    }

    /**
     * Take a post down.
     *
     * The win rows go by database cascade, but their media does not: the media
     * table is tied to a win by a polymorphic pair, which cannot carry a
     * foreign key, and a cascade fires no model events for anything downstream
     * to notice. So the media is taken away here, by hand, or the rows would be
     * left pointing at wins that no longer exist and the bytes would sit on the
     * disk for ever.
     */
    public function destroy(Request $request, Post $post, RecalculateWinStats $stats): JsonResponse
    {
        Gate::authorize('delete', $post);

        $discarded = [];

        DB::transaction(function () use ($post, &$discarded): void {
            $discarded = $this->detachMedia($this->mediaFor($post));

            $post->delete();
        });

        $this->discardFiles($discarded);

        $stats->execute($request->user());

        return response()->json(['data' => ['id' => $post->id]]);
    }

    /**
     * The circles a post should sit in, given who it is for.
     *
     * Public belongs to no circle. It is readable by everybody, which is a
     * different thing from being on a particular group's wall — that wall is
     * meant to be what the group was actually given, and a win that went out to
     * the world is not that. Putting one there is a second, deliberate act:
     * the circle's own screen offers it, for every public win not on it yet.
     *
     * "All circles" is resolved here, once, to the circles the author is in at
     * this moment, and is then just a list like any other. That is what makes
     * it a snapshot rather than a standing instruction: joining a circle next
     * month cannot reach back and hand it this win.
     *
     * @param  list<string>  $chosen  The ids named by the author, when they
     *                                picked the circles themselves.
     * @return list<string>
     */
    protected function circlesFor(User $author, string $visibility, array $chosen): array
    {
        return match ($visibility) {
            Post::VISIBILITY_PUBLIC => [],
            Post::VISIBILITY_ALL_CIRCLES => array_values(array_map(
                strval(...),
                $author->circles()->pluck('circles.id')->all(),
            )),
            Post::VISIBILITY_CUSTOM => $chosen,
            // Unreachable: validation has already narrowed it to the three.
            // Stated so a fourth added to the model without a branch here fails
            // loudly rather than quietly sharing a win with nobody.
            default => throw new InvalidArgumentException("Unknown visibility [{$visibility}]."),
        };
    }

    /**
     * Create or update one win on a post, and settle its media.
     *
     * @param  array<string, mixed>  $win
     * @return list<Media> The files this left behind, to unlink once the
     *                     surrounding transaction has committed.
     */
    protected function applyWin(Post $post, array $win): array
    {
        $shared = ['completed_at' => $this->completedAt($win)];
        $type = (string) $win['type'];

        /*
         * `updateOrCreate` against no attributes finds whatever row the post
         * already has of this kind, because the relation is scoped to the post
         * and the detail tables hold at most one row each.
         */
        $detail = match ($type) {
            'meditation' => $post->winMeditation()->updateOrCreate([], [
                ...$shared,
                'duration_minutes' => (int) $win['duration_minutes'],
                'completed' => (bool) ($win['completed'] ?? true),
            ]),
            'learning' => $post->winLearning()->updateOrCreate([], [
                ...$shared,
                'learned_text' => $win['learned_text'],
                'reference_source' => $win['reference_source'] ?? null,
            ]),
            'movement' => $post->winMovement()->updateOrCreate([], [
                ...$shared,
                'movement_type' => $win['movement_type'] ?? null,
            ]),
            // Unreachable: validation has already narrowed the type to one of
            // the three. Stated anyway so that a fourth kind added to the model
            // without a branch here fails loudly rather than silently doing
            // nothing to the post.
            default => throw new InvalidArgumentException("Unknown win type [{$type}]."),
        };

        $discarded = [];
        $removing = $win['remove_media_ids'] ?? [];

        if ($removing !== []) {
            $discarded = $this->detachMedia(
                $detail->media()
                    ->where('collection_name', $detail::MEDIA_COLLECTION)
                    ->whereIn('uuid', $removing)
                    ->get()
            );
        }

        // New files go after whatever survived the removals. Nothing has to be
        // renumbered behind them: the gaps a removal leaves are gaps in an
        // ordering column nobody reads directly, and the position a client is
        // told is worked out from where a file sits in the run.
        $this->attachMedia($detail, $win['media'] ?? []);

        // Derived rather than taken from the caller, exactly as on create.
        $detail->update([
            'media_attached' => $detail->media()
                ->where('collection_name', $detail::MEDIA_COLLECTION)
                ->exists(),
        ]);

        return $discarded;
    }

    /**
     * Drop a kind of win from a post entirely.
     *
     * The media is taken away first rather than left to the library, which
     * would unlink the files the moment the win goes — inside the transaction,
     * where a rollback could still put the rows back but nothing would bring
     * the bytes back with them.
     *
     * @return list<Media> The files this left behind.
     */
    protected function removeWin(Post $post, string $type): array
    {
        $detail = match ($type) {
            'meditation' => $post->winMeditation()->first(),
            'learning' => $post->winLearning()->first(),
            'movement' => $post->winMovement()->first(),
            default => null,
        };

        if ($detail === null) {
            return [];
        }

        $discarded = $this->detachMedia($detail->media()->get());

        $detail->deletePreservingMedia();

        return $discarded;
    }

    /**
     * Every file hanging off a post's wins.
     *
     * Found through the wins rather than the post, because the media table has
     * no column naming one. Win ids are uuids and so unique across every table,
     * which is what lets `model_id` alone say the row belongs here.
     *
     * @return Collection<int, Media>
     */
    protected function mediaFor(Post $post): Collection
    {
        $post->loadMissing(['winMeditation', 'winLearning', 'winMovement']);

        $winIds = array_values(array_filter([
            $post->winMeditation?->getKey(),
            $post->winLearning?->getKey(),
            $post->winMovement?->getKey(),
        ]));

        return Media::query()->whereIn('model_id', $winIds)->get();
    }

    /**
     * Take the rows for these files away, leaving the files themselves.
     *
     * Deleted through the query builder deliberately. Deleting a media model
     * fires the library's observer, which unlinks the file there and then, and
     * every caller here is inside a transaction that has not committed yet.
     * The rows go now; the bytes go once the database has agreed to the edit.
     *
     * @param  Collection<int, Media>  $media
     * @return list<Media>
     */
    protected function detachMedia(Collection $media): array
    {
        if ($media->isEmpty()) {
            return [];
        }

        Media::query()->whereKey($media->modelKeys())->delete();

        return array_values($media->all());
    }

    /**
     * Unlink files whose rows have already gone.
     *
     * @param  list<Media>  $media
     */
    protected function discardFiles(array $media): void
    {
        if ($media === []) {
            return;
        }

        $filesystem = app(Filesystem::class);

        foreach ($media as $file) {
            $filesystem->removeAllFiles($file);
        }
    }

    /**
     * How many of these wins are the first of their kind on the day they land.
     *
     * A pillar counts once a day. A second sitting on a Tuesday is still
     * posted, still in the feed and still its own row with its own photos — it
     * just does not move the total a second time, the same way it does not
     * move the streak or light a second ring on the week. The three pillars
     * are counted apart, so a day of all three is still worth three.
     *
     * {@see RecalculateWinStats} rebuilds the total by the same rule, which is
     * what stops an edit later disagreeing with what was counted here.
     *
     * @param  array<int, array<string, mixed>>  $wins
     */
    protected function firstOfTheirDay(User $user, array $wins): int
    {
        return collect($wins)
            ->reject(function (array $win) use ($user): bool {
                // The day it lands on is read on the display clock, and the
                // bounds are converted back to UTC for the column. Asked in
                // UTC, "the same day" runs from eight in the morning to eight
                // the next, so an evening win and the following morning's look
                // like one day and the second is not counted at all.
                $day = Day::startOf($this->completedAt($win));

                return $this->winModel((string) $win['type'])
                    ->newQuery()
                    // A subquery rather than `whereHas`, as everywhere else
                    // that asks this: the only thing wanted from the post is
                    // who wrote it.
                    ->whereIn('post_id', $user->posts()->select('posts.id'))
                    ->whereBetween('completed_at', [
                        Day::utc($day),
                        Day::utc($day->copy()->endOfDay()),
                    ])
                    ->exists();
            })
            ->count();
    }

    /**
     * When a win was completed, on the clock the column keeps.
     *
     * A caller may say when, and may say it with an offset. Eloquent writes
     * whatever wall clock the value carries and reads it back as UTC, so
     * anything arriving on another clock is stored shifted by its own offset —
     * `18:00+08:00` lands in the column as six in the evening UTC rather than
     * ten in the morning. Converted here, once, so the column is UTC whatever
     * came in and every day boundary drawn off it lands where it should.
     *
     * A bare `2026-07-28 18:00:00` with no offset is still read as UTC, which
     * is what it has always meant to this endpoint.
     *
     * @param  array<string, mixed>  $win
     */
    protected function completedAt(array $win): Carbon
    {
        return Day::utc(Carbon::parse($win['completed_at'] ?? now()));
    }

    /**
     * The model one kind of win is kept in.
     */
    protected function winModel(string $type): WinMeditation|WinLearning|WinMovement
    {
        return match ($type) {
            'meditation' => new WinMeditation,
            'learning' => new WinLearning,
            'movement' => new WinMovement,
            // Unreachable: validation has already narrowed the type to one of
            // the three. Stated so a fourth kind added to the model without a
            // branch here fails loudly rather than quietly counting nothing.
            default => throw new InvalidArgumentException("Unknown win type [{$type}]."),
        };
    }

    /**
     * Write the detail row for one win, and any files hanging off it.
     *
     * @param  array<string, mixed>  $win
     */
    protected function recordWin(Post $post, array $win): void
    {
        /** @var list<UploadedFile> $media */
        $media = $win['media'] ?? [];

        $shared = [
            // Derived, never taken from the caller: it simply records whether
            // this win ended up with files.
            'media_attached' => $media !== [],
            'completed_at' => $this->completedAt($win),
        ];

        $detail = match ($win['type']) {
            'meditation' => $post->winMeditation()->create([
                ...$shared,
                'duration_minutes' => (int) $win['duration_minutes'],
                'completed' => (bool) ($win['completed'] ?? true),
            ]),
            'learning' => $post->winLearning()->create([
                ...$shared,
                'learned_text' => $win['learned_text'],
                'reference_source' => $win['reference_source'] ?? null,
            ]),
            'movement' => $post->winMovement()->create([
                ...$shared,
                'movement_type' => $win['movement_type'] ?? null,
            ]),
            // Unreachable for the same reason it is on an edit: validation has
            // already narrowed the type to one of the three. Stated so that a
            // fourth kind added without a branch here fails loudly.
            default => throw new InvalidArgumentException("Unknown win type [{$win['type']}]."),
        };

        $this->attachMedia($detail, $media);
    }

    /**
     * Store the uploaded files for one win, keeping the order they arrived in.
     *
     * Each file is added on the end of what the win already holds, so the run
     * reads in upload order however many edits it took to build.
     *
     * Nothing here records what kind of file it is. That is read back off the
     * stored mime type when a win is rendered, which is one fewer thing to
     * disagree with the file itself — and a client could never mislabel a clip
     * as a photo even if it tried.
     *
     * @param  list<UploadedFile>  $media
     */
    protected function attachMedia(WinMeditation|WinLearning|WinMovement $win, array $media): void
    {
        foreach ($media as $file) {
            $win->addWinMedia($file);
        }
    }
}
