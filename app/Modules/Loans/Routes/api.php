<?php

use App\Modules\Loans\Controllers\AffordabilityController;
use App\Modules\Loans\Controllers\DisbursementController;
use App\Modules\Loans\Controllers\LoanAdminController;
use App\Modules\Loans\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

// Borrower: view own loans
Route::middleware(['auth:sanctum', 'permission:view_own_loans'])->group(function () {
    Route::get('/', [LoanController::class, 'index']);
    Route::get('/{loan}', [LoanController::class, 'show']);
    Route::post('/{loan}/cancel', [LoanController::class, 'cancel']);
    Route::get('/{loan}/disbursements', [DisbursementController::class, 'forLoan']);
});

// Borrower: request loans & calculate
Route::middleware(['auth:sanctum', 'permission:request_loan'])->group(function () {
    Route::post('/request', [LoanController::class, 'store']);
    Route::post('/calculate', [LoanController::class, 'calculate']);
});

// Borrower: affordability
Route::middleware(['auth:sanctum', 'permission:request_loan'])->group(function () {
    Route::post('/affordability/assess', [AffordabilityController::class, 'assess']);
    Route::post('/affordability/max-loan', [AffordabilityController::class, 'maxLoan']);
    Route::get('/affordability/history', [AffordabilityController::class, 'history']);
});

// Admin: manage loans
Route::middleware(['auth:sanctum', 'permission:manage_loans'])->group(function () {
    Route::get('/admin/pending', [LoanAdminController::class, 'index']);
    Route::get('/admin/marketplace', [LoanAdminController::class, 'marketplace']);
    Route::get('/admin/{loan}', [LoanAdminController::class, 'show']);
    Route::post('/admin/{loan}/approve', [LoanAdminController::class, 'approve']);
    Route::post('/admin/{loan}/reject', [LoanAdminController::class, 'reject']);
    Route::post('/admin/{loan}/affordability', [AffordabilityController::class, 'assessForLoan']);
});

// Admin: disbursement management
Route::middleware(['auth:sanctum', 'permission:manage_loans'])->prefix('disbursements')->group(function () {
    Route::get('/pending', [DisbursementController::class, 'pending']);
    Route::get('/failed-retry', [DisbursementController::class, 'failedRetry']);
    Route::get('/reconciliation-report', [DisbursementController::class, 'reconciliationReport']);
    Route::get('/{disbursement}', [DisbursementController::class, 'show']);
    Route::post('/{disbursement}/retry', [DisbursementController::class, 'retry']);
    Route::post('/{disbursement}/reconcile', [DisbursementController::class, 'reconcile']);
});
