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
        Schema::create('meditation_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label')->unique();
            $table->string('slug')->unique();
            $table->string('icon');
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meditation_categories');
    }
};
