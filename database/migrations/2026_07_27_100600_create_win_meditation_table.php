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
        Schema::create('win_meditation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('completed')->default(false);
            $table->boolean('media_attached')->default(false);
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('win_meditation');
    }
};
