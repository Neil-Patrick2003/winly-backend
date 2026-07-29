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
        Schema::create('win_media', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The morph says which win owns the row. post_id is carried
            // alongside it purely so the database can cascade the cleanup:
            // a polymorphic column cannot hold a foreign key, and deleting a
            // post cascades to the win tables without firing model events.
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('win');

            $table->string('url', 2048);
            $table->string('kind', 16);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['win_type', 'win_id', 'position'], 'win_media_win_position_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('win_media');
    }
};
