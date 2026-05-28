<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public catalog endpoints
    Route::prefix('public')->group(function () {
        Route::get('categories', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'index']);
        Route::get('categories/tree', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'tree']);
        Route::get('categories/{category:slug}/services', [\App\Http\Controllers\Api\V1\Public\CategoryController::class, 'services']);
        Route::get('services', [\App\Http\Controllers\Api\V1\Public\ServiceController::class, 'index']);
        Route::get('services/{service:slug}', [\App\Http\Controllers\Api\V1\Public\ServiceController::class, 'show']);
        Route::get('providers/search', [\App\Http\Controllers\Api\V1\Public\ProviderController::class, 'search']);
        Route::get('providers/{id}', [\App\Http\Controllers\Api\V1\Public\ProviderController::class, 'show']);
    });

    // Public auth endpoints
    Route::post('auth/register',        [AuthController::class, 'register']);
    Route::post('auth/verify-otp',      [AuthController::class, 'verifyOtp']);
    Route::post('auth/resend-otp',      [AuthController::class, 'resendOtp'])->middleware('throttle:3,60');
    Route::post('auth/login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);

    // Protected endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user',    [AuthController::class, 'user']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('profile',      [ProfileController::class, 'show']);
        Route::put('profile',      [ProfileController::class, 'update']);
    });
});
