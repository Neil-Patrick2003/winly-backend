<?php

namespace App\Actions;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Writes the in-app notifications somebody's own activity earns them.
 *
 * One place rather than scattered `Notification::create` calls, because the
 * rules are the same wherever they are raised: never notify yourself, and never
 * let one person stack the same unread notice twice.
 */
class RecordNotification
{
    public function __construct(protected SendPushNotification $push) {}

    /**
     * Somebody followed you.
     *
     * Keyed on the pair, so unfollowing and following again refreshes the one
     * notice rather than adding another — a follow button is easy to fidget
     * with and nobody wants a column of the same line.
     */
    public function follow(User $recipient, User $actor): ?Notification
    {
        return $this->write($recipient, $actor, 'follow', null, "{$actor->full_name} started following you");
    }

    /**
     * Somebody liked your post. Keyed on (actor, post) for the same reason.
     */
    public function like(User $recipient, User $actor, Post $post): ?Notification
    {
        return $this->write($recipient, $actor, 'like', $post, "{$actor->full_name} liked your win");
    }

    /**
     * Somebody commented on your post.
     *
     * Not keyed: two comments are two things said, and collapsing them would
     * hide the second. This is the one kind that can legitimately repeat.
     */
    public function comment(User $recipient, User $actor, Post $post): ?Notification
    {
        if ($recipient->is($actor)) {
            return null;
        }

        return $this->deliver(Notification::create([
            'user_id' => $recipient->getKey(),
            'actor_id' => $actor->getKey(),
            'type' => 'comment',
            'post_id' => $post->getKey(),
            'message' => "{$actor->full_name} commented on your win",
        ]));
    }

    /**
     * Write one, unless it would be addressed to the person who caused it.
     *
     * An unread notice for the same actor, type and post is refreshed rather
     * than repeated: its timestamp moves so it sorts back to the top, and the
     * list stays one line per thing that happened.
     */
    protected function write(
        User $recipient,
        User $actor,
        string $type,
        ?Post $post,
        string $message,
    ): ?Notification {
        // Liking your own win is allowed; being told about it is not.
        if ($recipient->is($actor)) {
            return null;
        }

        $notification = Notification::firstOrNew([
            'user_id' => $recipient->getKey(),
            'actor_id' => $actor->getKey(),
            'type' => $type,
            'post_id' => $post?->getKey(),
        ]);

        $notification->message = $message;
        $notification->is_read = false;
        // Touched even when the row already existed, so a repeat sorts to the
        // top rather than staying buried where it was first written.
        $notification->created_at = now();
        $notification->save();

        return $this->deliver($notification);
    }

    /**
     * Push it to their devices as well as writing it down.
     *
     * The row is the record and the push is the nudge; the push failing must
     * never take the record with it, which is why delivery swallows its own
     * errors rather than throwing here.
     */
    protected function deliver(Notification $notification): Notification
    {
        // Straight down the socket to whoever is holding one open, so the bell
        // moves as it happens rather than on the next poll.
        //
        // Guarded for the same reason the push below is: `ShouldBroadcastNow`
        // publishes inline, so an unreachable Reverb throws inside the request
        // that caused it. The like or follow has already committed by then, and
        // letting the throw out turns a write that succeeded into a 500 the
        // caller reads as failure — then a retry answers 200, because the
        // second attempt is not new and never reaches here at all.
        try {
            NotificationCreated::dispatch($notification);
        } catch (\Throwable $caught) {
            Log::warning('Broadcast failed', [
                'notification' => $notification->getKey(),
                'error' => $caught->getMessage(),
            ]);
        }

        $this->push->execute($notification);

        return $notification;
    }
}
