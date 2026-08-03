<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hand the wins' photos and clips over to the media library.
 *
 * `win_media` held a URL and worked the path back out of it whenever a file had
 * to be deleted, which only held while everything sat on one public disk. The
 * library keeps the disk and the file name as columns instead, so this moves
 * the rows onto that footing and the files into the layout it expects.
 *
 * A row whose file is not on the disk is left behind rather than carried over
 * as a record of nothing. Those rows are reported at the end, so a run that
 * skipped something says so instead of looking like a clean sweep.
 *
 * The files may be landing somewhere other than where they started: everything
 * `win_media` held was written to the local public disk, and the library writes
 * to whichever disk it is pointed at. Where those differ each file is streamed
 * across rather than read into memory, because a win may be carrying a clip of
 * a hundred megabytes.
 */
return new class extends Migration
{
    /**
     * The disk the wins' files have always been written to.
     */
    protected const SOURCE_DISK = 'public';

    /**
     * The folder they were written to within it.
     */
    protected const FOLDER = 'win-media';

    /**
     * The mime type each extension stands for.
     *
     * `win_media` recorded only whether a row was a photo or a clip, and the
     * library wants the type itself. The file name is the only thing left that
     * still knows, so it is read from there and falls back to the kind.
     *
     * @var array<string, string>
     */
    protected const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
    ];

    public function up(): void
    {
        $skipped = [];
        $target = $this->mediaDisk();

        foreach (DB::table('win_media')->orderBy('win_id')->orderBy('position')->cursor() as $row) {
            $path = $this->pathFor($row->url);

            if ($path === null || ! Storage::disk(self::SOURCE_DISK)->exists($path)) {
                $skipped[] = $row->id;

                continue;
            }

            $name = basename($path);

            /*
             * The row is written before the file moves, because the library
             * files everything under the media row's own key and there is no
             * key to file it under until the insert has happened.
             *
             * `uuid` carries the old row's id across. That is the id the API
             * has been handing out, so a client holding one can still name the
             * same photo after this runs.
             */
            $id = DB::table('media')->insertGetId([
                'model_type' => $row->win_type,
                'model_id' => $row->win_id,
                'uuid' => $row->id,
                'collection_name' => 'win',
                'name' => pathinfo($name, PATHINFO_FILENAME),
                'file_name' => $name,
                'mime_type' => $this->mimeFor($name, $row->kind),
                'disk' => $target,
                'conversions_disk' => null,
                'size' => Storage::disk(self::SOURCE_DISK)->size($path),
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                // The library numbers from one, and `win_media` numbered from
                // zero. Only the order matters, but keeping to its convention
                // means new files land past these rather than among them.
                'order_column' => (int) $row->position + 1,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->moveFile(self::SOURCE_DISK, $path, $target, $this->libraryPath($id, $name));
        }

        if ($skipped !== []) {
            $this->report($skipped);
        }

        Schema::dropIfExists('win_media');
    }

    public function down(): void
    {
        Schema::create('win_media', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('win');

            $table->string('url', 2048);
            $table->string('kind', 16);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['win_type', 'win_id', 'position'], 'win_media_win_position_index');
        });

        $rows = DB::table('media')->where('collection_name', 'win')->orderBy('id')->get();

        /*
         * `win_media` carried the post as well as the win, and the library does
         * not, so it is read back off the win each row hangs on. A win whose
         * post has since gone takes its media with it: the column is a foreign
         * key, and there is nothing to point it at.
         */
        $posts = $this->postsForWins(array_values(array_unique(
            array_map(fn (object $row): string => (string) $row->model_id, $rows->all())
        )));

        foreach ($rows as $row) {
            $postId = $posts[$row->model_id] ?? null;

            if ($postId === null) {
                continue;
            }

            $path = $this->libraryPath($row->id, $row->file_name);

            // Back to the disk `win_media` knew about, from whichever one the
            // row says it ended up on.
            $from = (string) $row->disk;

            if (Storage::disk($from)->exists($path)) {
                $this->moveFile($from, $path, self::SOURCE_DISK, self::FOLDER.'/'.$row->file_name);
            }

            DB::table('win_media')->insert([
                'id' => $row->uuid ?? (string) Str::uuid(),
                'post_id' => $postId,
                'win_type' => $row->model_type,
                'win_id' => $row->model_id,
                'url' => url(Storage::disk(self::SOURCE_DISK)->url(self::FOLDER.'/'.$row->file_name)),
                'kind' => str_starts_with((string) $row->mime_type, 'video/') ? 'video' : 'image',
                'position' => max(0, (int) $row->order_column - 1),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        DB::table('media')->where('collection_name', 'win')->delete();
    }

    /**
     * The post each of these wins belongs to, keyed by win id.
     *
     * @param  list<string>  $winIds
     * @return array<string, string>
     */
    protected function postsForWins(array $winIds): array
    {
        if ($winIds === []) {
            return [];
        }

        $posts = [];

        foreach (['win_meditation', 'win_learning', 'win_movement'] as $table) {
            foreach (DB::table($table)->whereIn('id', $winIds)->get(['id', 'post_id']) as $win) {
                $posts[$win->id] = $win->post_id;
            }
        }

        return $posts;
    }

    /**
     * The disk the library has been pointed at.
     */
    protected function mediaDisk(): string
    {
        return (string) config('media-library.disk_name');
    }

    /**
     * Carry one file from one disk to another.
     *
     * Streamed rather than read whole where the disks differ, so a large clip
     * does not have to fit in memory to be moved. On one disk it is a rename,
     * which is what `move` already does.
     */
    protected function moveFile(string $fromDisk, string $from, string $toDisk, string $to): void
    {
        if ($fromDisk === $toDisk) {
            Storage::disk($fromDisk)->move($from, $to);

            return;
        }

        $stream = Storage::disk($fromDisk)->readStream($from);

        if ($stream === null) {
            return;
        }

        Storage::disk($toDisk)->writeStream($to, $stream);

        // Some adapters close the handle themselves, so this only tidies up
        // after the ones that leave it open.
        if (is_resource($stream)) {
            fclose($stream);
        }

        Storage::disk($fromDisk)->delete($from);
    }

    /**
     * Where the library keeps one file.
     */
    protected function libraryPath(int|string $mediaId, string $fileName): string
    {
        $prefix = trim((string) config('media-library.prefix'), '/');

        return ($prefix === '' ? '' : $prefix.'/').$mediaId.'/'.$fileName;
    }

    /**
     * The path on the disk that a stored URL names, where one can be worked out.
     *
     * The last thing in this application to do it this way. Anything not under
     * the disk's own prefix is not ours to move, and says so by answering null.
     */
    protected function pathFor(string $url): ?string
    {
        $prefix = parse_url(Storage::disk(self::SOURCE_DISK)->url(''), PHP_URL_PATH) ?: '/';
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, $prefix)) {
            return null;
        }

        return ltrim(substr($path, strlen($prefix)), '/');
    }

    /**
     * The mime type to record for one file.
     */
    protected function mimeFor(string $fileName, string $kind): string
    {
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension]
            ?? ($kind === 'video' ? 'video/mp4' : 'image/jpeg');
    }

    /**
     * Say which rows were left behind, and why.
     *
     * @param  list<string>  $skipped
     */
    protected function report(array $skipped): void
    {
        $message = sprintf(
            '%d win_media row(s) had no file on the %s disk and were not carried over: %s',
            count($skipped),
            self::SOURCE_DISK,
            implode(', ', $skipped),
        );

        if (app()->runningInConsole()) {
            echo PHP_EOL.'  '.$message.PHP_EOL;
        }

        logger()->warning($message);
    }
};
