<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStoryRequest;
use App\Http\Resources\Api\V1\StoryResource;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    /**
     * The disk story photos are written to.
     */
    protected const DISK = 'public';

    /**
     * The folder within that disk.
     */
    protected const FOLDER = 'stories';

    /**
     * Share a story.
     *
     * The expiry is set here rather than accepted from the caller: how long a
     * story lasts is the product's decision, not the client's.
     */
    public function store(StoreStoryRequest $request): JsonResponse
    {
        $path = $request->file('image')->store(self::FOLDER, self::DISK);

        $story = $request->user()->stories()->create([
            'image_url' => url(Storage::disk(self::DISK)->url($path)),
            'caption' => $request->validated('caption'),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ]);

        return (new StoryResource($story))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Take a story down before it expires.
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        Gate::authorize('delete', $story);

        $story->delete();

        return response()->json(['data' => ['id' => $story->id]]);
    }
}
