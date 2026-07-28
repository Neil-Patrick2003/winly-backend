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
        Schema::create('story_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_type');
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_reactions');
    }
};
