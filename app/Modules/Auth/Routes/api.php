<?php

use App\Modules\Auth\Controllers\ReferralController;
use App\Modules\Auth\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

// Public registration routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/register/validate-referral', [RegisterController::class, 'validateReferralCode']);
Route::post('/register/send-otp', [RegisterController::class, 'sendPhoneOtp']);
Route::post('/register/verify-otp', [RegisterController::class, 'verifyPhoneOtp']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/verify-phone', [RegisterController::class, 'verifyPhoneOtp']);
    Route::get('/referral/my-code', [ReferralController::class, 'myCode']);
    Route::get('/referral/my-referrals', [ReferralController::class, 'myReferrals']);
});
