<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the person agreed to the Terms and the Privacy Policy.
     *
     * Null for everyone who signed up before agreeing was asked of them.
     *
     * Guarded, because an earlier unmerged branch added the same column and
     * some databases already have it.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'terms_accepted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'terms_accepted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
