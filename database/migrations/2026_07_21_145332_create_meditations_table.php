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
        Schema::create('meditations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('meditation_categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
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
        Schema::dropIfExists('meditations');
    }
};
