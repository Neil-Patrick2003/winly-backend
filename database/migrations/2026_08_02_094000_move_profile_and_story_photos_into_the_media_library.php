<?php

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hand the profile and story photos over to the media library.
 *
 * The last three columns holding an address rather than a file. A stored URL is
 * a copy of a decision made when it was written — which disk, which host — and
 * it goes stale the moment either changes. Moving these onto the library leaves
 * the address to be worked out on the way out, and takes the orphaned files
 * with it: replacing an avatar used to leave the old one on the disk for ever.
 *
 * A row whose file is not on the disk is left behind rather than carried over
 * as a record of nothing. That includes rows pointing somewhere that was never
 * ours to serve, which seeded data tends to be full of. They are reported at
 * the end, so a run that skipped something says so.
 */
return new class extends Migration
{
    /**
     * The disk these files have always been written to.
     */
    protected const SOURCE_DISK = 'public';

    /**
     * Each column being retired: where its files sit, and where they are going.
     *
     * @var list<array{table: string, column: string, folder: string, model: class-string, collection: string}>
     */
    protected const SOURCES = [
        [
            'table' => 'users',
            'column' => 'avatar_url',
            'folder' => 'avatars',
            'model' => User::class,
            'collection' => User::AVATAR_COLLECTION,
        ],
        [
            'table' => 'users',
            'column' => 'cover_url',
            'folder' => 'covers',
            'model' => User::class,
            'collection' => User::COVER_COLLECTION,
        ],
        [
            'table' => 'stories',
            'column' => 'image_url',
            'folder' => 'stories',
            'model' => Story::class,
            'collection' => Story::IMAGE_COLLECTION,
        ],
    ];

    /**
     * The mime type each extension stands for.
     *
     * None of these columns recorded a type, so the file name is the only thing
     * left that still knows.
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
    ];

    public function up(): void
    {
        $skipped = [];
        $target = $this->mediaDisk();

        foreach (self::SOURCES as $source) {
            $rows = DB::table($source['table'])
                ->whereNotNull($source['column'])
                ->orderBy('id')
                ->get(['id', $source['column']]);

            foreach ($rows as $row) {
                $path = $this->pathFor((string) $row->{$source['column']});

                if ($path === null || ! Storage::disk(self::SOURCE_DISK)->exists($path)) {
                    $skipped[] = $source['table'].'.'.$source['column'].' #'.$row->id;

                    continue;
                }

                $name = basename($path);

                // The row goes first: the library files everything under the
                // media row's own key, and there is no key until it exists.
                $id = DB::table('media')->insertGetId([
                    'model_type' => $source['model'],
                    'model_id' => $row->id,
                    'uuid' => (string) Str::uuid(),
                    'collection_name' => $source['collection'],
                    'name' => pathinfo($name, PATHINFO_FILENAME),
                    'file_name' => $name,
                    'mime_type' => $this->mimeFor($name),
                    'disk' => $target,
                    'conversions_disk' => null,
                    'size' => Storage::disk(self::SOURCE_DISK)->size($path),
                    'manipulations' => '[]',
                    'custom_properties' => '[]',
                    'generated_conversions' => '[]',
                    'responsive_images' => '[]',
                    // One file per collection, so there is nothing to order
                    // against. The library numbers from one.
                    'order_column' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->moveFile(self::SOURCE_DISK, $path, $target, $this->libraryPath($id, $name));
            }
        }

        if ($skipped !== []) {
            $this->report($skipped);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_url', 'cover_url']);
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('password_hash');
            $table->string('cover_url')->nullable()->after('cover_gradient');
        });

        /*
         * Nullable, where it began as required. A story whose photo did not
         * survive the round trip has no address to put back, and a column that
         * refused to say so would have to invent one.
         */
        Schema::table('stories', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->after('user_id');
        });

        foreach (self::SOURCES as $source) {
            $rows = DB::table('media')
                ->where('model_type', $source['model'])
                ->where('collection_name', $source['collection'])
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $to = $source['folder'].'/'.$row->file_name;
                $path = $this->libraryPath($row->id, $row->file_name);
                $from = (string) $row->disk;

                if (Storage::disk($from)->exists($path)) {
                    $this->moveFile($from, $path, self::SOURCE_DISK, $to);
                }

                DB::table($source['table'])
                    ->where('id', $row->model_id)
                    ->update([
                        $source['column'] => url(Storage::disk(self::SOURCE_DISK)->url($to)),
                    ]);
            }

            DB::table('media')
                ->where('model_type', $source['model'])
                ->where('collection_name', $source['collection'])
                ->delete();
        }
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
     * Streamed where the disks differ, so nothing has to fit in memory to be
     * moved. On one disk it is a rename, which is what `move` already does.
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
     * Anything not under the disk's own prefix is not ours to move, and says so
     * by answering null — a seeded row pointing at some CDN that never existed
     * is exactly the case this is here to catch.
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
    protected function mimeFor(string $fileName): string
    {
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));

        return self::MIME_TYPES[$extension] ?? 'image/jpeg';
    }

    /**
     * Say which rows were left behind, and why.
     *
     * @param  list<string>  $skipped
     */
    protected function report(array $skipped): void
    {
        $message = sprintf(
            '%d photo(s) had no file on the %s disk and were not carried over: %s',
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
