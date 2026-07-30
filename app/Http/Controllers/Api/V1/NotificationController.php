<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCircleRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * What has happened to this person's things, newest first.
     *
     * Read as well as unread: an alerts list that emptied itself the moment it
     * was opened would take away the only record of who liked what.
     *
     * @return AnonymousResourceCollection<int, NotificationResource>
     */
    public function index(IndexCircleRequest $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->notifications()
            ->with(['actor' => fn (Relation $query) => $query->withActiveStory()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    /**
     * How many are still unread — what the bell wears as a badge.
     *
     * Its own endpoint because it is polled far more often than the list is
     * read, and a count is a great deal cheaper than a page of rows.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread' => $request->user()->notifications()->unread()->count()],
        ]);
    }

    /**
     * Mark everything read.
     *
     * All at once rather than one at a time: the badge answers "is there
     * anything new", and opening the list is the answer to it.
     */
    public function markRead(Request $request): JsonResponse
    {
        $request->user()->notifications()->unread()->update(['is_read' => true]);

        return response()->json(['data' => ['unread' => 0]]);
    }

    /**
     * Take one off the list.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 404);

        $notification->delete();

        return response()->json(['data' => ['id' => $notification->getKey()]]);
    }
}
