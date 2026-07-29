<?php

use App\Models\CircleInvitation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations, blocks, and posts belonging to a circle.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('circle_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default(CircleInvitation::PENDING);
            /** Null while it is still waiting on an answer. */
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            /*
             * One invitation per person per circle. Re-inviting someone who
             * declined updates the row rather than stacking a second one up,
             * so a circle cannot be used to pester somebody.
             */
            $table->unique(['circle_id', 'invitee_id']);
            // The notifications screen asks "what is waiting on me", so the
            // index leads with the person being asked.
            $table->index(['invitee_id', 'status']);
        });

        Schema::create('circle_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            /** Whoever blocked them; kept for the record, not for display. */
            $table->foreignUuid('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['circle_id', 'user_id']);
        });

        Schema::table('posts', function (Blueprint $table) {
            /*
             * The circle a post was shared into, or null for one shared with
             * everybody. Nullable because that is what every post written
             * before circles existed is, and what most posts will go on being.
             */
            $table->foreignUuid('circle_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['circle_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['circle_id', 'created_at']);
            $table->dropConstrainedForeignId('circle_id');
        });

        Schema::dropIfExists('circle_blocks');
        Schema::dropIfExists('circle_invitations');
    }
};
