<?php

use App\Modules\Repayments\Controllers\RepaymentAdminController;
use App\Modules\Repayments\Controllers\RepaymentController;
use Illuminate\Support\Facades\Route;

// Admin: manage repayments (defined first to avoid route collision)
Route::middleware(['auth:sanctum', 'permission:manage_repayments'])->group(function () {
    Route::get('/all', [RepaymentAdminController::class, 'index']);
    Route::get('/admin/{repayment}', [RepaymentAdminController::class, 'show']);
    Route::get('/admin/overdue/summary', [RepaymentAdminController::class, 'overdueSummary']);
    Route::get('/admin/upcoming', [RepaymentAdminController::class, 'upcoming']);
    Route::post('/admin/check-overdue', [RepaymentAdminController::class, 'triggerOverdueCheck']);
    Route::post('/admin/{repayment}/mark-defaulted', [RepaymentAdminController::class, 'markDefaulted']);
    Route::post('/admin/{repayment}/waive-penalty', [RepaymentAdminController::class, 'waivePenalty']);
});

// Lender: view earnings
Route::middleware(['auth:sanctum', 'permission:view_own_portfolio'])->group(function () {
    Route::get('/lender/earnings', [RepaymentController::class, 'lenderEarnings']);
    Route::get('/lender/summary', [RepaymentController::class, 'lenderSummary']);
});

// Borrower: make repayments (defined last)
Route::middleware(['auth:sanctum', 'permission:make_repayment'])->group(function () {
    Route::get('/', [RepaymentController::class, 'index']);
    Route::get('/schedule/{loan}', [RepaymentController::class, 'schedule']);
    Route::post('/', [RepaymentController::class, 'store']);
    Route::get('/{repayment}', [RepaymentController::class, 'show']);
});
