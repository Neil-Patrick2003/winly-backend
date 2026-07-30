<?php

use App\Http\Controllers\CircleController;
use App\Http\Controllers\CircleManagementController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostLikeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
