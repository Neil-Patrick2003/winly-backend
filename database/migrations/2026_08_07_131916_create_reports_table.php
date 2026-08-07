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
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();

            /*
             * What is being reported — a post, a comment, a story or a person.
             *
             * Polymorphic rather than four tables or four nullable columns: the
             * queue staff work through does not care which kind a row is until
             * the moment it is opened, and every new thing that can be reported
             * would otherwise mean another column and another branch.
             *
             * `uuidMorphs` rather than `morphs`, because every id in this schema
             * is a UUID and an integer column would silently truncate them.
             */
            $table->uuidMorphs('reportable');

            $table->string('reason');
            /** What the reporter added in their own words, if anything. */
            $table->text('note')->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /*
             * One report per person per thing.
             *
             * Not to save space — to keep the queue honest. Without it, a
             * person who taps report twice makes the same content look twice as
             * reported as it is, and the number staff triage by stops meaning
             * anything.
             */
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_one_per_reporter');

            // The queue is read as "pending, oldest first" — the 24 hours
            // promised in the terms is measured from `created_at`.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
