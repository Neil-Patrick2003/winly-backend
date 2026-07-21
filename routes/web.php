<?php

use App\Http\Controllers\MeditationCategoryController;
use App\Http\Controllers\MeditationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('meditation-categories', MeditationCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('meditations', MeditationController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
