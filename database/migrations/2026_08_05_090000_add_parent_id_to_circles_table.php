<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A circle may sit inside another one.
 *
 * A sub-circle is a smaller room in a bigger house: its members are drawn from
 * the parent's, and what is said in it carries to the parent as well. It has an
 * owner of its own, which the parent's owner picks and may change.
 *
 * One level deep, and the column is what enforces it — a circle with a parent
 * may not itself be a parent. That is checked where sub-circles are written
 * rather than by the database, which cannot express it, but the shape is why
 * every read here is a single hop and nothing recurses.
 *
 * Deleting a parent takes its sub-circles with it. They exist inside it and
 * have no meaning once it is gone; the cascade also carries their memberships
 * and their share of `circle_post`, which already cascade from a circle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->foreignUuid('parent_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('circles')
                ->cascadeOnDelete();

            // Every sub-circle listing asks the same question of it: "which
            // circles sit inside this one".
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
