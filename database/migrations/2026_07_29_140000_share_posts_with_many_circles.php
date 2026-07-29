<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A post is shared into any number of circles, not one.
 *
 * The single `posts.circle_id` could only ever express "this belongs to that
 * group", which meant sharing the same win with three circles meant writing it
 * three times — three rows, three comment threads, three sets of likes. A pivot
 * lets one post reach all of them while staying one post.
 *
 * The reading side has to be written carefully off the back of this: joining
 * the pivot to filter a feed returns a post once per circle it matches, so
 * somebody in five of the author's circles would see the same win five times.
 * Every read uses an existence subquery instead. See `Post::visibleTo`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('circle_post', function (Blueprint $table) {
            /*
             * No surrogate key: the pair is the identity, and a plain
             * `belongsToMany` writes only the two columns — a uuid primary key
             * would have nothing to fill it and every attach would fail on the
             * not-null constraint.
             *
             * One row per post per circle, so sharing into the same circle
             * twice is not two shares.
             */
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('circle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['post_id', 'circle_id']);
            $table->index(['circle_id', 'post_id']);
        });

        // Carry across everything already shared into a circle.
        DB::table('posts')
            ->whereNotNull('circle_id')
            ->orderBy('id')
            ->chunkById(200, function ($posts): void {
                DB::table('circle_post')->insert(
                    collect($posts)
                        ->map(fn ($post): array => [
                            'post_id' => $post->id,
                            'circle_id' => $post->circle_id,
                            'created_at' => $post->created_at,
                            'updated_at' => $post->updated_at,
                        ])
                        ->all()
                );
            });

        /*
         * Three statements, in this order, because MySQL will not let go of an
         * index a foreign key is still leaning on: the constraint has to be
         * dropped before the index it uses, and the column only once nothing
         * refers to it.
         */
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['circle_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['circle_id', 'created_at']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('circle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignUuid('circle_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['circle_id', 'created_at']);
        });

        // Only one can survive the trip back; the earliest share wins.
        foreach (DB::table('circle_post')->orderBy('created_at')->orderBy('id')->get() as $row) {
            DB::table('posts')
                ->where('id', $row->post_id)
                ->whereNull('circle_id')
                ->update(['circle_id' => $row->circle_id]);
        }

        Schema::dropIfExists('circle_post');
    }
};
