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
            $table->foreignUuid('meditation_item_id')
                ->nullable()
                ->constrained('meditation_items')
                ->nullOnDelete();
            $table->boolean('media_attached')->default(false);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index('meditation_item_id');
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
