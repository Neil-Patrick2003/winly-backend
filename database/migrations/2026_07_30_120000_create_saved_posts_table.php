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
        Schema::create('saved_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Saving twice is one save, and the index is what makes that true
            // under a double tap rather than only in the controller.
            $table->unique(['user_id', 'post_id']);
            /*
             * The one question this table is asked: what has this person saved,
             * most recently first. `created_at` is in the key because that is
             * what the list is ordered and paged by — the id breaks ties on it.
             */
            $table->index(['user_id', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_posts');
    }
};
