<?php

use App\Http\Controllers\ChirpController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('chirps/{chirp}/attachments/{attachment}/thumbnail', [ChirpController::class, 'attachmentThumbnail'])
        ->whereNumber('attachment')
        ->name('chirps.attachments.thumbnail');
    Route::get('chirps/{chirp}/attachments/{attachment}', [ChirpController::class, 'attachment'])
        ->whereNumber('attachment')
        ->name('chirps.attachments.show');
    Route::resource('chirps', ChirpController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
