<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordWinStreak;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPostRequest;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Models\WinMedia;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        ];
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
        $posts = Post::query()
            ->with($this->relationsFor($request->user()))
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
     */
    protected function attachMedia(Post $post, Model $win, array $media): void
    {
        foreach ($media as $position => $file) {
            $path = $file->store(self::MEDIA_FOLDER, self::MEDIA_DISK);

            $win->media()->create([
                'post_id' => $post->id,
                'url' => url(Storage::disk(self::MEDIA_DISK)->url($path)),
                'kind' => WinMedia::kindForMime($file->getMimeType()),
                'position' => $position,
            ]);
        }
    }
}
