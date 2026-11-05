<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Somebody flagging a post, comment, story or person for staff to review.
     *
     * Skipped where the table already exists: an earlier unmerged branch made
     * one with these same columns, and some databases still have it. Nothing
     * is dropped, so no report already filed is lost.
     */
    public function up(): void
    {
        if (Schema::hasTable('reports')) {
            return;
        }

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->uuidMorphs('reportable');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_one_per_reporter');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
