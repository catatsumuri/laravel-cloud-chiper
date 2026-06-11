<?php

use App\Http\Controllers\ChirpController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('chirps', ChirpController::class)->only(['index', 'store', 'destroy']);
});

require __DIR__.'/settings.php';
