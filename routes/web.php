<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('callback');
});
