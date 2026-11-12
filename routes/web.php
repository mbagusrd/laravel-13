<?php

use App\Http\Controllers\Auth\FirebaseAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/auth/firebase/verify-token', [FirebaseAuthController::class, 'verifyToken'])
    ->name('auth.firebase.verify');
