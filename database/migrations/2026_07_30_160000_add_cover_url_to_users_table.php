<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Beside `cover_gradient` rather than replacing it: the gradient is what a
     * profile wears until somebody uploads something, and every account that
     * exists today has one. Dropping it would leave those profiles blank.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cover_url')->nullable()->after('cover_gradient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cover_url');
        });
    }
};
