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
use App\Models\WinMedia;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PostController extends Controller
{
    /**
     * The disk uploaded photos and clips are written to.
     */
    protected const MEDIA_DISK = 'public';

    /**
     * The folder within that disk.
     */
    protected const MEDIA_FOLDER = 'win-media';

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
            'user' => fn (Relation $query) => $query->withActiveStory(),
            'user.followers' => fn (Relation $query) => $query->whereKey($viewer->getKey()),
            // One eager load for the whole page, so a feed of circle posts
            // costs one query rather than one per post.
            'circles',
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
            ->with($this->relationsFor($request->user()))
            ->latestFirst()
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PostResource::collection($posts);
    }

    /**
     * One person's own posts, newest first.
     *
     * What a profile shows. Nothing is narrowed: a circle is where a post is
     * *placed*, not who it is kept from, so a profile lists everything its
     * owner has shared.
     *
     * @return AnonymousResourceCollection<int, PostResource>
     */
    public function byUser(IndexPostRequest $request, User $user): AnonymousResourceCollection
    {
        $posts = $user->posts()
            ->with($this->relationsFor($request->user()))
            ->latestFirst()
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

        $posts = Post::query()
            ->with($this->relationsFor($viewer))
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
     * three wins at once is still one day shown up for.
     */
    public function store(StorePostRequest $request, RecordWinStreak $streak): JsonResponse
    {
        $post = DB::transaction(function () use ($request, $streak): Post {
            $user = $request->user();

            $post = $user->posts()->create($request->safe()->only(['caption']));

            /*
             * One post, attached to every circle it was shared with. Attaching
             * rather than duplicating is what keeps a win shared with ten
             * circles one thing — one comment thread, one set of likes, and one
             * row in anybody's feed.
             */
            $circleIds = $request->validated('circle_ids') ?? [];
            if ($circleIds !== []) {
                $post->circles()->sync($circleIds);
            }

            $wins = $request->validated('wins');

            foreach ($wins as $win) {
                $this->recordWin($post, $win);
            }

            $user->increment('wins_count', count($wins));

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
         * paths are collected while the rows go, and only unlinked once the
         * database has agreed to the whole edit.
         */
        $discarded = [];

        DB::transaction(function () use ($request, $post, &$discarded): void {
            $post->update($request->safe()->only(['caption']));

            /*
             * Only touched when the caller says something about it. Sending an
             * empty list means "no circles"; saying nothing at all means "leave
             * the sharing alone", which is what a client editing only the text
             * of a post is doing.
             */
            if ($request->exists('circle_ids')) {
                $post->circles()->sync($request->validated('circle_ids') ?? []);
            }

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

        $this->deleteStoredMedia($discarded);

        $stats->execute($request->user());

        return new PostResource($post->fresh($this->relationsFor($request->user())));
    }

    /**
     * Take a post down.
     *
     * The rows go by database cascade, which is also why the files have to be
     * gathered first: a cascade fires no model events, so nothing downstream
     * ever hears that the media is gone and the bytes would sit on the disk
     * for ever.
     */
    public function destroy(Request $request, Post $post, RecalculateWinStats $stats): JsonResponse
    {
        Gate::authorize('delete', $post);

        $discarded = $this->pathsFor(WinMedia::query()->where('post_id', $post->getKey())->get());

        DB::transaction(fn () => $post->delete());

        $this->deleteStoredMedia($discarded);

        $stats->execute($request->user());

        return response()->json(['data' => ['id' => $post->id]]);
    }

    /**
     * Create or update one win on a post, and settle its media.
     *
     * @param  array<string, mixed>  $win
     * @return list<string> The stored paths this left behind, to unlink once
     *                      the surrounding transaction has committed.
     */
    protected function applyWin(Post $post, array $win): array
    {
        $shared = ['completed_at' => $win['completed_at'] ?? (string) now()];
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
            $doomed = $detail->media()->whereIn('id', $removing)->get();
            $discarded = $this->pathsFor($doomed);
            $detail->media()->whereIn('id', $removing)->delete();
        }

        // New files go after what is already there, then the whole run is
        // renumbered to close the gaps the removals left.
        $this->attachMedia($post, $detail, $win['media'] ?? [], ((int) $detail->media()->max('position')) + 1);
        $this->resequenceMedia($detail);

        // Derived rather than taken from the caller, exactly as on create.
        $detail->update(['media_attached' => $detail->media()->exists()]);

        return $discarded;
    }

    /**
     * Drop a kind of win from a post entirely.
     *
     * The media has to go explicitly. `win_media` is tied to the post by
     * foreign key and to the win only by a polymorphic pair, which cannot
     * carry one — so deleting the win alone would leave its rows orphaned and
     * still counted.
     *
     * @return list<string> The stored paths this left behind.
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

        $discarded = $this->pathsFor($detail->media()->get());

        $detail->media()->delete();
        $detail->delete();

        return $discarded;
    }

    /**
     * Renumber a win's media from zero, keeping the order it was in.
     *
     * Removing the second of four photos would otherwise leave positions 0, 2,
     * 3, and a client drawing them in position order has no way to tell that
     * from a gap it should render.
     */
    protected function resequenceMedia(WinMeditation|WinLearning|WinMovement $win): void
    {
        $win->media()->orderBy('position')->orderBy('id')->get()
            ->each(function (WinMedia $media, int $position): void {
                if ($media->position !== $position) {
                    $media->update(['position' => $position]);
                }
            });
    }

    /**
     * The path on the media disk for each row, where one can be worked out.
     *
     * Rows store an absolute URL, which is what clients need, so getting back
     * to a path means undoing that. Anything that does not sit under the disk's
     * own prefix is skipped rather than guessed at — a row pointing somewhere
     * else is not ours to delete.
     *
     * @param  Collection<int, WinMedia>  $media
     * @return list<string>
     */
    protected function pathsFor(Collection $media): array
    {
        $prefix = parse_url(Storage::disk(self::MEDIA_DISK)->url(''), PHP_URL_PATH) ?: '/';

        $paths = [];

        foreach ($media as $row) {
            $path = parse_url($row->url, PHP_URL_PATH);

            if (! is_string($path) || ! str_starts_with($path, $prefix)) {
                continue;
            }

            $paths[] = ltrim(substr($path, strlen($prefix)), '/');
        }

        return $paths;
    }

    /**
     * Remove stored files, having already removed the rows that named them.
     *
     * @param  list<string>  $paths
     */
    protected function deleteStoredMedia(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        Storage::disk(self::MEDIA_DISK)->delete($paths);
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
            'completed_at' => $win['completed_at'] ?? (string) now(),
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
        };

        $this->attachMedia($post, $detail, $media);
    }

    /**
     * Store the uploaded files for one win, keeping the order they arrived in.
     *
     * The kind is read from the file rather than taken from the caller, so a
     * client cannot mislabel a clip as a photo.
     *
     * @param  list<UploadedFile>  $media
     * @param  int  $from  The position to start numbering at. Non-zero when
     *                     editing, where the new files go after whatever the
     *                     win is already holding.
     */
    protected function attachMedia(Post $post, Model $win, array $media, int $from = 0): void
    {
        foreach ($media as $offset => $file) {
            $path = $file->store(self::MEDIA_FOLDER, self::MEDIA_DISK);

            $win->media()->create([
                'post_id' => $post->id,
                'url' => url(Storage::disk(self::MEDIA_DISK)->url($path)),
                'kind' => WinMedia::kindForMime($file->getMimeType()),
                'position' => $from + $offset,
            ]);
        }
    }
}
