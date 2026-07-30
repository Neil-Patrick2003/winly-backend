<?php

namespace App\Events;

use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A notification, the moment it is written, pushed to whoever it is for.
 *
 * This is what makes the alerts screen and the bell live rather than polled:
 * the client holds an open socket and is told, instead of asking every twenty
 * seconds and being up to twenty seconds wrong.
 *
 * `ShouldBroadcastNow`, not `ShouldBroadcast`: the queued form would sit in the
 * jobs table until a worker picked it up, and this project runs
 * `QUEUE_CONNECTION=database` with no worker — so every notification would
 * simply never arrive, silently. Publishing inline costs the request a few
 * milliseconds against a local socket server. Swap it back to the queued
 * interface the day there is a worker to run it.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    /**
     * A private channel per person.
     *
     * Notifications name who liked what and who followed whom, so the channel
     * has to be one only its owner can subscribe to — see `routes/channels.php`
     * for the check that enforces it.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * The same shape the REST endpoint returns.
     *
     * One shape means the client can drop a broadcast row straight into the
     * list it already fetched, without a second mapping to keep in step.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->notification->loadMissing('actor');

        return [
            'notification' => (new NotificationResource($this->notification))->toArray(request()),
        ];
    }
}
