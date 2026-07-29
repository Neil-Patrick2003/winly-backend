<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The day a streak was last credited. A date rather than a timestamp,
     * because a streak counts days: it is the only part of when a win landed
     * that the streak cares about.
     *
     * `last_active_at` cannot stand in for this. It moves whenever the user
     * so much as opens the app, which would let a streak survive on attention
     * alone rather than on wins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('last_win_on')->nullable()->after('longest_streak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_win_on');
        });
    }
};
