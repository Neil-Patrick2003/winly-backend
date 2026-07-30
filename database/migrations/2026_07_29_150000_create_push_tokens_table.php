<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where to reach somebody when the app is not open.
 *
 * One row per device, not per person: the same account signed in on a phone and
 * a tablet should be reachable on both, and a token belongs to an install
 * rather than to a user — which is why the token itself is unique and simply
 * moves if the same device signs in as somebody else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            /** An Expo push token: `ExponentPushToken[…]`. */
            $table->string('token')->unique();
            $table->string('platform')->nullable();
            /** Cleared whenever Expo tells us the token is dead. */
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
