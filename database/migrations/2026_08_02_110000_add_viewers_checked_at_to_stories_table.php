<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the author last looked at who had watched.
 *
 * The ring around your own story used to be lit for as long as the story was
 * up, which made it a restatement of something you already knew — you posted
 * it. This is what lets it mean something instead: somebody has watched since
 * you last looked.
 *
 * Null means never looked, and every view counts as new. That is the right
 * reading for a story just posted, and for every story that existed before
 * this column did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->timestamp('viewers_checked_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('viewers_checked_at');
        });
    }
};
