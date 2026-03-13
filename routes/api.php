<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ShoppingController;
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

// Product routes
Route::apiResource('products', ProductController::class);

// Public shopping store routes
Route::get('/shopping', [ShoppingController::class, 'index'])->name('shopping.index');

// Protected purchase routes
Route::prefix('purchases')->middleware('auth:api')->controller(PurchaseController::class)->group(function () {
    Route::get('/', 'index')->name('purchases.index');
    Route::post('/', 'store')->name('purchases.store');
    Route::put('/{transaction}', 'update')->name('purchases.update');
});
