<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostLikeController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\StoryReactionController;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('api.v1.')->group(function () {
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        Route::get('user', fn (Request $request) => new UserResource($request->user()->loadActiveStory()))
            ->name('user');

        Route::get('stories', [StoryController::class, 'index'])
            ->name('stories.index');

        Route::post('stories', [StoryController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('stories.store');

        Route::post('stories/{story}/view', [StoryController::class, 'view'])
            ->middleware('throttle:120,1')
            ->name('stories.view');

        Route::get('stories/{story}/views', [StoryController::class, 'viewers'])
            ->name('stories.viewers');

        Route::put('stories/{story}/reaction', [StoryReactionController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('stories.react');

        Route::delete('stories/{story}/reaction', [StoryReactionController::class, 'destroy'])
            ->middleware('throttle:120,1')
            ->name('stories.unreact');

        Route::delete('stories/{story}', [StoryController::class, 'destroy'])
            ->name('stories.destroy');

        Route::get('posts', [PostController::class, 'index'])
            ->name('posts.index');

        Route::get('posts/{post}', [PostController::class, 'show'])
            ->name('posts.show');

        Route::post('posts', [PostController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('posts.store');

        Route::get('profile', [ProfileController::class, 'me'])
            ->name('profile.show');

        Route::patch('profile', [ProfileController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('profile.update');

        Route::get('users/{user}', [ProfileController::class, 'show'])
            ->name('users.show');

        Route::get('users/{user}/following', [FollowController::class, 'following'])
            ->name('users.following');

        Route::get('users/{user}/followers', [FollowController::class, 'followers'])
            ->name('users.followers');

        Route::post('users/{user}/follow', [FollowController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('users.follow');

        Route::delete('users/{user}/follow', [FollowController::class, 'destroy'])
            ->middleware('throttle:60,1')
            ->name('users.unfollow');

        Route::put('posts/{post}/like', [PostLikeController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('posts.like');

        Route::delete('posts/{post}/like', [PostLikeController::class, 'destroy'])
            ->middleware('throttle:120,1')
            ->name('posts.unlike');

        Route::get('posts/{post}/comments', [CommentController::class, 'index'])
            ->name('posts.comments.index');

        Route::post('posts/{post}/comments', [CommentController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('posts.comments.store');

        Route::patch('comments/{comment}', [CommentController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('comments.update');

        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
            ->middleware('throttle:60,1')
            ->name('comments.destroy');
    });
});
