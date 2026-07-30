<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communities become circles, and gain an owner.
 *
 * A rename rather than an edit of the original migrations: those have already
 * run everywhere the app is installed, so changing them would leave a database
 * that disagrees with its own history. The tables carried no controllers or
 * routes yet, so nothing but the seeder and the models had to follow.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('communities', 'circles');
        Schema::rename('community_memberships', 'circle_memberships');

        Schema::table('circle_memberships', function (Blueprint $table) {
            $table->renameColumn('community_id', 'circle_id');
        });

        Schema::table('circles', function (Blueprint $table) {
            /*
             * Nullable, because the circles seeded before this migration have
             * nobody who made them. A circle without an owner is simply one
             * nobody may rename or take down — the read paths do not care.
             */
            $table->foreignUuid('owner_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_private')->default(false)->after('tag');

            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('is_private');
        });

        Schema::table('circle_memberships', function (Blueprint $table) {
            $table->renameColumn('circle_id', 'community_id');
        });

        Schema::rename('circle_memberships', 'community_memberships');
        Schema::rename('circles', 'communities');
    }
};
