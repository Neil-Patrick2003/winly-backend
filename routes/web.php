<?php

use App\Http\Controllers\CircleController;
use App\Http\Controllers\CircleManagementController;
use App\Http\Controllers\Dashboard\ActivityFeedController;
use App\Http\Controllers\Dashboard\ActivityOverviewController;
use App\Http\Controllers\Dashboard\CirclesStatController;
use App\Http\Controllers\Dashboard\DailyPostsStatController;
use App\Http\Controllers\Dashboard\EngagementStatController;
use App\Http\Controllers\Dashboard\MemberOverviewController;
use App\Http\Controllers\Dashboard\MembersStatController;
use App\Http\Controllers\Dashboard\MyCirclesController;
use App\Http\Controllers\Dashboard\StreakLeadersController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostLikeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    /*
     * The console page ships no data of its own. Each tile fetches from its own
     * endpoint, so one slow aggregate delays a single tile instead of the page,
     * and a tile can be refreshed without reloading the rest.
     */
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('dashboard/stats')->as('dashboard.stats.')->group(function () {
        Route::get('circles', CirclesStatController::class)->name('circles');
        Route::get('members', MembersStatController::class)->name('members');
        Route::get('posts', DailyPostsStatController::class)->name('posts');
        Route::get('engagement', EngagementStatController::class)->name('engagement');

        Route::get('overview', ActivityOverviewController::class)->name('overview');
        Route::get('member-overview', MemberOverviewController::class)->name('member-overview');
        Route::get('my-circles', MyCirclesController::class)->name('my-circles');
        Route::get('streak-leaders', StreakLeadersController::class)->name('streak-leaders');
        Route::get('activity', ActivityFeedController::class)->name('activity');
    });

    Route::get('circles', [CircleController::class, 'index'])->name('circles.index');
    Route::post('circles', [CircleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('circles.store');

    Route::put('posts/{post}/like', [PostLikeController::class, 'store'])->name('posts.like');
    Route::delete('posts/{post}/like', [PostLikeController::class, 'destroy'])->name('posts.unlike');
    Route::post('posts/{post}/comments', [PostCommentController::class, 'store'])->name('posts.comments.store');
    Route::delete('comments/{comment}', [PostCommentController::class, 'destroy'])->name('comments.destroy');

    Route::prefix('circles/{circle}')->as('circles.')->group(function () {
        Route::get('/', [CircleController::class, 'members'])->name('members');
        Route::get('posts', [CircleController::class, 'posts'])->name('posts');
        Route::get('tracker', [CircleController::class, 'tracker'])->name('tracker');

        Route::get('manage', [CircleManagementController::class, 'edit'])->name('manage');
        Route::patch('manage', [CircleManagementController::class, 'update'])->name('manage.update');

        Route::post('invitations', [CircleManagementController::class, 'invite'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [CircleManagementController::class, 'revokeInvitation'])->name('invitations.destroy');

        Route::delete('members/{user}', [CircleManagementController::class, 'removeMember'])->name('members.remove');
        Route::post('blocks/{user}', [CircleManagementController::class, 'block'])->name('blocks.store');
        Route::delete('blocks/{user}', [CircleManagementController::class, 'unblock'])->name('blocks.destroy');

        Route::delete('/', [CircleManagementController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
