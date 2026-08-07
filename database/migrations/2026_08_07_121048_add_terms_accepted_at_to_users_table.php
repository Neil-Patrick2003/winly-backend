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
            /*
             * When this account agreed to the terms.
             *
             * A timestamp rather than a boolean, because the question that gets
             * asked later is never "did they agree" on its own — it is "did
             * they agree, and to which version", and the date is what answers
             * that against a dated document.
             *
             * Nullable, and null for everyone who registered before this
             * shipped. Backfilling it with a date would be recording consent
             * that was never given, which is worse than having no record.
             */
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
