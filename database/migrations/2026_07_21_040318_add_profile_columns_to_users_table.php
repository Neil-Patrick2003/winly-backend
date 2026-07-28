<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('full_name');
            $table->string('avatar_url')->nullable()->after('password_hash');
            $table->text('bio')->nullable()->after('avatar_url');
            $table->string('cover_gradient')->nullable()->after('bio');
            $table->unsignedInteger('streak_days')->default(0)->after('cover_gradient');
            $table->unsignedInteger('longest_streak')->default(0)->after('streak_days');
            $table->unsignedInteger('followers_count')->default(0)->after('longest_streak');
            $table->unsignedInteger('following_count')->default(0)->after('followers_count');
            $table->unsignedInteger('wins_count')->default(0)->after('following_count');
            $table->boolean('is_private')->default(false)->after('wins_count');
            $table->boolean('is_admin')->default(false)->after('is_private');
            $table->timestamp('last_active_at')->nullable()->after('is_admin');
            $table->softDeletes();

            $table->index('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'avatar_url',
                'bio',
                'cover_gradient',
                'streak_days',
                'longest_streak',
                'followers_count',
                'following_count',
                'wins_count',
                'is_private',
                'is_admin',
                'last_active_at',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
