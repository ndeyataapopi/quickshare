<?php

use App\Modules\KYC\Controllers\KycAdminController;
use App\Modules\KYC\Controllers\KycController;
use Illuminate\Support\Facades\Route;

// Authenticated users with submit_kyc permission
Route::middleware(['auth:sanctum', 'permission:submit_kyc'])->group(function () {
    Route::post('/submit', [KycController::class, 'submit']);
    Route::post('/{submission}/resubmit', [KycController::class, 'resubmit']);
    Route::get('/status', [KycController::class, 'status']);
    Route::get('/documents/{document}/download', [KycController::class, 'downloadDocument']);
});

// Admin & Compliance: KYC review
Route::middleware(['auth:sanctum', 'permission:approve_kyc'])->group(function () {
    Route::get('/pending', [KycAdminController::class, 'pending']);
    Route::get('/submissions/{submission}', [KycAdminController::class, 'show']);
    Route::post('/submissions/{submission}/review', [KycAdminController::class, 'review']);
    Route::get('/submissions/{submission}/documents/{document}/download', [KycController::class, 'downloadDocument']);
});
