<?php

use App\Http\Controllers\Admin\AccountStatusController;
use App\Http\Controllers\Admin\CircleController as AdminCircleController;
use App\Http\Controllers\Admin\CircleOwnershipController;
use App\Http\Controllers\Admin\PasswordResetLinkController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\Stats\AccountsStatController;
use App\Http\Controllers\Admin\Stats\ActiveAccountsStatController;
use App\Http\Controllers\Admin\Stats\CirclesStatController as AdminCirclesStatController;
use App\Http\Controllers\Admin\Stats\DailyPostsStatController as AdminDailyPostsStatController;
use App\Http\Controllers\Admin\Stats\EngagementStatController as AdminEngagementStatController;
use App\Http\Controllers\Admin\Stats\LiveStreaksStatController;
use App\Http\Controllers\Admin\Stats\PostsSeriesController;
use App\Http\Controllers\Admin\Stats\SignupsSeriesController;
use App\Http\Controllers\Admin\Stats\SignupsStatController;
use App\Http\Controllers\Admin\Stats\WinMixController;
use App\Http\Controllers\Admin\Stats\WinsLoggedStatController;
use App\Http\Controllers\Admin\UserController;
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
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostLikeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

/*
 * The Privacy Policy and the Terms, public so the app stores and the sign-up
 * screen can link to them before anybody has an account.
 */
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/delete-account', [LegalController::class, 'deleteAccount'])->name('legal.delete-account');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
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

        // Circles inside this one. Made here rather than from the phone: it
        // decides who ends up able to read a group's wins, and that is a
        // decision to make sitting down.
        Route::post('sub-circles', [CircleManagementController::class, 'createSubCircle'])
            ->name('sub.store');

        Route::post('invitations', [CircleManagementController::class, 'invite'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [CircleManagementController::class, 'revokeInvitation'])->name('invitations.destroy');

        Route::delete('members/{user}', [CircleManagementController::class, 'removeMember'])->name('members.remove');
        // Taking a reported post off this circle's wall. Local to the circle:
        // deleting it everywhere is staff's call, from the Reports page.
        Route::delete('posts/{post}', [CircleManagementController::class, 'removePost'])->name('posts.remove');
        // Who else runs the place. The owner's to decide, unlike handing the
        // circle over — that one is staff's, below.
        Route::patch('members/{user}/role', [CircleManagementController::class, 'setMemberRole'])->name('members.role');
        Route::post('blocks/{user}', [CircleManagementController::class, 'block'])->name('blocks.store');
        Route::delete('blocks/{user}', [CircleManagementController::class, 'unblock'])->name('blocks.destroy');

        Route::delete('/', [CircleManagementController::class, 'destroy'])->name('destroy');
    });

    /*
     * Staff only, and the one thing nobody inside a circle can do for
     * themselves: the policy asks the owner for permission to change the owner,
     * so a circle whose owner has gone quiet has no way out from the inside.
     */
    Route::middleware('admin')->prefix('admin')->as('admin.')->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        /*
         * One endpoint per tile, matching the owner console: a slow aggregate
         * holds up its own number rather than the page, and a failure shows in
         * the tile it belongs to.
         */
        Route::prefix('stats')->as('stats.')->group(function () {
            Route::get('signups', SignupsStatController::class)->name('signups');
            Route::get('active', ActiveAccountsStatController::class)->name('active');
            Route::get('posts', AdminDailyPostsStatController::class)->name('posts');
            Route::get('streaks', LiveStreaksStatController::class)->name('streaks');
            Route::get('accounts', AccountsStatController::class)->name('accounts');
            Route::get('circles', AdminCirclesStatController::class)->name('circles');
            Route::get('wins', WinsLoggedStatController::class)->name('wins');
            Route::get('engagement', AdminEngagementStatController::class)->name('engagement');
            Route::get('win-mix', WinMixController::class)->name('win-mix');
            Route::get('signups-series', SignupsSeriesController::class)->name('signups-series');
            Route::get('posts-series', PostsSeriesController::class)->name('posts-series');
        });

        Route::get('circles', [AdminCircleController::class, 'index'])->name('circles');
        Route::post('circles', [AdminCircleController::class, 'store'])->name('circles.store');
        /*
         * The transfer lives on the circle's own manage screen rather than on a
         * staff-only page: everything else about running a circle is already
         * there, and staff reach that screen for any circle through the policy.
         */
        Route::patch('circles/{circle}/owner', CircleOwnershipController::class)->name('circles.owner');

        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::post('users/{user}/reset-link', PasswordResetLinkController::class)->name('users.reset-link');
        Route::post('users/{user}/disable', [AccountStatusController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/enable', [AccountStatusController::class, 'enable'])->name('users.enable');

        /*
         * The moderation queue. Each decision closes every open report about
         * the same thing, so one tap answers a pile-on.
         */
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports');
        Route::post('reports/{report}/remove', [AdminReportController::class, 'remove'])->name('reports.remove');
        Route::post('reports/{report}/disable', [AdminReportController::class, 'disable'])->name('reports.disable');
        Route::post('reports/{report}/dismiss', [AdminReportController::class, 'dismiss'])->name('reports.dismiss');
    });
});

require __DIR__.'/settings.php';
