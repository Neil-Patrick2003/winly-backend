<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\CircleController;
use App\Http\Controllers\Api\V1\CircleInvitationController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\DiscoverController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostLikeController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
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

        Route::get('user', fn (Request $request) => new UserResource($request->user()->loadActiveStory()->loadCount('posts')))
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

        Route::get('progress/week', [ProgressController::class, 'week'])
            ->name('progress.week');

        Route::get('discover', [DiscoverController::class, 'index'])
            ->name('discover');

        Route::get('circles', [CircleController::class, 'index'])
            ->name('circles.index');

        Route::post('circles', [CircleController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('circles.store');

        Route::get('circles/{circle}', [CircleController::class, 'show'])
            ->name('circles.show');

        Route::delete('circles/{circle}', [CircleController::class, 'destroy'])
            ->name('circles.destroy');

        Route::get('circles/{circle}/members', [CircleController::class, 'members'])
            ->name('circles.members');

        Route::get('circles/{circle}/posts', [PostController::class, 'circle'])
            ->name('circles.posts');

        Route::delete('circles/{circle}/members/{user}', [CircleController::class, 'removeMember'])
            ->middleware('throttle:60,1')
            ->name('circles.members.remove');

        Route::get('circles/{circle}/blocks', [CircleController::class, 'blocked'])
            ->name('circles.blocks.index');

        Route::post('circles/{circle}/blocks/{user}', [CircleController::class, 'block'])
            ->middleware('throttle:60,1')
            ->name('circles.blocks.store');

        Route::delete('circles/{circle}/blocks/{user}', [CircleController::class, 'unblock'])
            ->middleware('throttle:60,1')
            ->name('circles.blocks.destroy');

        Route::get('circles/{circle}/friends', [CircleInvitationController::class, 'friends'])
            ->name('circles.friends');

        Route::post('circles/{circle}/invitations', [CircleInvitationController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('circles.invitations.store');

        Route::get('invitations', [CircleInvitationController::class, 'index'])
            ->name('invitations.index');

        Route::post('invitations/{invitation}/accept', [CircleInvitationController::class, 'accept'])
            ->middleware('throttle:60,1')
            ->name('invitations.accept');

        Route::post('invitations/{invitation}/decline', [CircleInvitationController::class, 'decline'])
            ->middleware('throttle:60,1')
            ->name('invitations.decline');

        Route::post('circles/{circle}/membership', [CircleController::class, 'join'])
            ->middleware('throttle:60,1')
            ->name('circles.join');

        Route::delete('circles/{circle}/membership', [CircleController::class, 'leave'])
            ->middleware('throttle:60,1')
            ->name('circles.leave');

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

        Route::get('users/{user}/posts', [PostController::class, 'byUser'])
            ->name('users.posts');

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
