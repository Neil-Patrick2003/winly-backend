<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One person blocking another, across the whole app.
     *
     * Different from `circle_blocks`, which bars somebody from a single circle.
     * This one hides the two people from each other everywhere: posts,
     * comments, stories, profiles, search and notifications.
     */
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->foreignUuid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // The pair is the key: a pivot row has nothing else to identify it,
            // and blocking somebody twice should change nothing.
            $table->primary(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
