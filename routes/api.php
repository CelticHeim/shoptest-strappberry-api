<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/login', 'login')->name('auth.login');
});

// Protected auth routes
Route::prefix('auth')->middleware('auth:api')->controller(AuthController::class)->group(function () {
    Route::get('/user', 'user')->name('auth.user');
    Route::post('/refresh', 'refresh')->name('auth.refresh');
    Route::post('/logout', 'logout')->name('auth.logout');
});

