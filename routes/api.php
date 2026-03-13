<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
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
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('auth:api')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
});

// Public shopping store routes
Route::get('/shopping', [ShoppingController::class, 'index'])->name('shopping.index');

// Protected checkout routes (customer only)
Route::prefix('checkout')->middleware(['auth:api', 'customer'])->controller(CheckoutController::class)->group(function () {
    Route::post('/', 'createPreference')->name('checkout.create');
    Route::get('/verify-payment/{payment_id}', 'verifyPayment')->name('checkout.verify');
    Route::post('/confirm', 'confirmPurchase')->name('checkout.confirm');
});

// Protected purchase routes (customer only)
Route::prefix('purchases')->middleware(['auth:api', 'customer'])->controller(PurchaseController::class)->group(function () {
    Route::get('/', 'index')->name('purchases.index');
    Route::put('/{transaction}', 'update')->name('purchases.update');
});
