<?php

use App\Modules\Marketplace\Controllers\MarketplaceController;
use Illuminate\Support\Facades\Route;

// View marketplace listings
Route::middleware(['auth:sanctum', 'permission:view_marketplace'])->group(function () {
    Route::get('/', [MarketplaceController::class, 'index']);
    Route::get('/stats', [MarketplaceController::class, 'stats']);
    Route::get('/{loan}', [MarketplaceController::class, 'show']);
});

// Lender: fund a loan (placeholder for funding module)
Route::middleware(['auth:sanctum', 'permission:fund_loan'])->group(function () {
    // Route::post('/{loan}/fund', [FundingController::class, 'fund']);
});
