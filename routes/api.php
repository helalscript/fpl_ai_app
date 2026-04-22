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
    Route::get('/assets/badge/{teamId}.svg', [FplController::class, 'teamBadgeImage']);
    Route::get('/assets/jersey/{teamId}.svg', [FplController::class, 'teamJerseyImage']);
    // home page
    Route::get('/home-page', [FplController::class, 'homePage']);
    Route::get('/home-page/gameweek-details', [FplController::class, 'gameweekDetails']);
    Route::get('/home-page/transfer-suggestions', [FplController::class, 'transferSuggestions']);
    Route::get('/home-page/captaincy-suggestion', [FplController::class, 'captaincySuggestion']);
    Route::get('/home-page/squad-recommendation', [FplController::class, 'squadRecommendation']);
    Route::get('/home-page/bench-players', [FplController::class, 'benchPlayers']);

    // fixtures page
    Route::get('/fixtures-page', [FplController::class, 'fixturesPage']);
    Route::get('/fixtures-page/list', [FplController::class, 'fixturesList']);
    Route::get('/fixtures-page/fdr', [FplController::class, 'fixturesFdr']);

    // transfer page
    Route::get('/transfer-page', [FplController::class, 'transferPage']);
    Route::get('/transfer-page/primary', [FplController::class, 'transferPrimary']);
    Route::get('/transfer-page/options', [FplController::class, 'transferOptions']);
    Route::get('/transfer-page/insights', [FplController::class, 'transferInsights']);

    // optimizer page
    Route::get('/optimizer/recommended-captain', [FplController::class, 'recommendedCaptain']);
    Route::get('/optimizer/recommended-vice-captain', [FplController::class, 'recommendedViceCaptain']);
    Route::get('/optimizer/starting-xi', [FplController::class, 'optimizedStartingXI']);
    Route::get('/optimizer/bench', [FplController::class, 'optimizedBench']);
    Route::get('/optimizer/insights', [FplController::class, 'optimizerInsights']);

    // dashboard
    Route::post('/dashboard', [FplController::class, 'dashboard']);

    // chat
    Route::post('/chat', [FplController::class, 'chat']);
    Route::post('/chat/stream', [FplController::class, 'chatStream']);

    // players
    Route::get('/players', [FplController::class, 'players']);
    Route::get('/player/{playerId}', [FplController::class, 'getPlayerData']);
});
