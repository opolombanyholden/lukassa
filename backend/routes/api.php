<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

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
