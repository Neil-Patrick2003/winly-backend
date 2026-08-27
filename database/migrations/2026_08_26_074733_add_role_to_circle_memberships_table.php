<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give a membership a rank, so a circle can be run by more than one person.
     *
     * Ownership was the `circles.owner_id` column and nothing else, which left
     * room for exactly one person. The column stays and still names the one who
     * made it — who is shown as the owner, who cannot be turned out, and who a
     * handover moves. What it no longer decides on its own is who may run the
     * place: that is this rank, and any number of members can hold it.
     */
    public function up(): void
    {
        Schema::table('circle_memberships', function (Blueprint $table): void {
            // A string rather than an enum: adding a rank later should be a
            // deploy rather than a table rewrite.
            $table->string('role', 20)->default('member')->after('circle_id');

            // The one question this column is asked — who runs this circle —
            // and the answer is a handful of rows out of possibly thousands.
            $table->index(['circle_id', 'role']);
        });

        /*
         * Whoever already owned a circle holds the rank in it.
         *
         * Without this every existing owner would read as an ordinary member
         * the moment the policy starts asking about the rank, and a circle
         * would look unrun to the person who built it.
         *
         * An owner with no membership row is left alone rather than invented:
         * `owner_id` still speaks for them, and a row here would claim they
         * joined on a date nobody recorded.
         */
        DB::table('circle_memberships')
            ->whereIn('circle_id', DB::table('circles')
                ->whereNotNull('owner_id')
                ->whereColumn('circles.owner_id', 'circle_memberships.user_id')
                ->select('circles.id'))
            ->update(['role' => 'owner']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('circle_memberships', function (Blueprint $table): void {
            $table->dropIndex(['circle_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
