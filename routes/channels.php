<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Who may listen to what.
 *
 * One private channel per person, carrying their notifications. The check is
 * the whole of the authorisation: without it anybody could subscribe to
 * anybody's channel and read who is following and liking whom.
 */
Broadcast::channel('users.{userId}', function (User $user, string $userId): bool {
    return $user->getKey() === $userId;
});
