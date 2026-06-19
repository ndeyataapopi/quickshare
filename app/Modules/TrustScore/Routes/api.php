<?php

use App\Modules\TrustScore\Controllers\TrustScoreController;
use Illuminate\Support\Facades\Route;

// Authenticated users: view own trust score
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-score', [TrustScoreController::class, 'myScore']);
    Route::get('/my-history', [TrustScoreController::class, 'myHistory']);
});

// Admin: view any user's trust score
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/users/{user}', [TrustScoreController::class, 'show']);
    Route::get('/users/{user}/history', [TrustScoreController::class, 'userHistory']);
});
