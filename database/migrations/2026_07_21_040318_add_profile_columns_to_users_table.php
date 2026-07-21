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
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('avatar')->nullable()->after('password');
            $table->string('cover_photo')->nullable()->after('avatar');
            $table->text('bio')->nullable()->after('cover_photo');
            $table->boolean('is_private')->default(false)->after('bio');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar', 'cover_photo', 'bio', 'is_private']);
            $table->dropSoftDeletes();
        });
    }
};
