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
        Schema::create('meditation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')
                ->constrained('meditation_categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'title']);
            $table->index('created_at');
            $table->index('duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meditation_items');
    }
};
