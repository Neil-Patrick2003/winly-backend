<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Say who a post is for.
 *
 * Until now a circle was where a win was *placed* and never who it was kept
 * from: every read said so in as many words, and a post shared into a circle
 * was still listed on its author's profile for anyone at all. This column is
 * what turns that placement into a boundary.
 *
 * `all_circles` and `custom` are the same rule — the members of the circles the
 * post was shared into — and differ only in what the author was asked. Keeping
 * them apart is what lets an edit screen show the choice that was actually
 * made rather than guessing it back from the list.
 *
 * Existing rows become `custom`, the closed option. A post already in a circle
 * carries on reaching that circle; a post in none reaches nobody but its
 * author. That is the deliberate choice: widening an audience by default is
 * not a thing a migration should do quietly, and narrowing one is undone by
 * re-sharing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('visibility', 16)
                ->default(Post::VISIBILITY_CUSTOM)
                ->after('caption');

            /*
             * Paired with `created_at` because it is never asked on its own:
             * every feed reads "the public ones, newest first", and an index on
             * the flag alone would still leave the ordering to a filesort.
             */
            $table->index(['visibility', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'created_at']);
            $table->dropColumn('visibility');
        });
    }
};
