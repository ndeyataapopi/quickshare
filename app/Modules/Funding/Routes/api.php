<?php

use App\Modules\Funding\Controllers\FundingController;
use Illuminate\Support\Facades\Route;

// Lender: manage funding & portfolio
Route::middleware(['auth:sanctum', 'permission:fund_loan'])->group(function () {
    Route::get('/', [FundingController::class, 'index']);
    Route::post('/{loan}', [FundingController::class, 'store']);
    Route::get('/{fundingTransaction}', [FundingController::class, 'show']);
    Route::post('/{fundingTransaction}/cancel', [FundingController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'permission:view_own_portfolio'])->group(function () {
    Route::get('/portfolio/summary', [FundingController::class, 'portfolio']);
});

// Public: view loan funding details
Route::middleware(['auth:sanctum', 'permission:view_marketplace'])->group(function () {
    Route::get('/loan/{loan}/fundings', [FundingController::class, 'loanFundings']);
});
