<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the columns the owner console counts over.
 *
 * Every dashboard statistic is a range scan on `completed_at` or a filter on
 * `streak_days`, and none of those columns carried an index — each tile meant a
 * full table scan.
 */
return new class extends Migration
{
    /**
     * The win detail tables, each holding one `completed_at` per post.
     *
     * @var list<string>
     */
    private const WIN_TABLES = ['win_meditation', 'win_learning', 'win_movement'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::WIN_TABLES as $winTable) {
            Schema::table($winTable, function (Blueprint $table): void {
                $table->index('completed_at');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->index('streak_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::WIN_TABLES as $winTable) {
            Schema::table($winTable, function (Blueprint $table): void {
                $table->dropIndex(['completed_at']);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['streak_days']);
        });
    }
};
