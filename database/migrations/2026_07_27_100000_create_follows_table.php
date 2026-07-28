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
        Schema::create('follows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'followee_id']);
            $table->index('followee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
