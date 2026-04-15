<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SocialLoginController;
use App\Http\Controllers\API\V1\User\FplController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // register
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('/verify-email', [RegisterController::class, 'VerifyEmail']);
    Route::post('/resend-otp', [RegisterController::class, 'ResendOtp']);

    // login
    Route::post('login', [LoginController::class, 'login']);

    // forgot password
    Route::post('/forget-password', [ResetPasswordController::class, 'forgotPassword']);
    Route::post('/verify-otp', [ResetPasswordController::class, 'VerifyOTP']);
    Route::post('/reset-password', [ResetPasswordController::class, 'ResetPassword']);

    // social login
    Route::post('/social-login', [SocialLoginController::class, 'SocialLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
    });


});

    Route::prefix('fpl')->group(function () {
    // Route::prefix('fpl')->middleware('auth:sanctum')->group(function () {
        Route::post('/dashboard', [FplController::class, 'dashboard']);
        Route::post('/chat', [FplController::class, 'chat']);
        Route::get('/players', [FplController::class, 'getAllPlayerData']);
        Route::get('/player/{playerId}', [FplController::class, 'getPlayerData']);
    });