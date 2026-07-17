<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Webhooks\MifosWebhookController;
use App\Modules\Funding\Controllers\FundingController;
use App\Modules\KYC\Controllers\KycAdminController;
use App\Modules\KYC\Controllers\KycController;
use App\Modules\Loans\Controllers\AffordabilityController;
use App\Modules\Loans\Controllers\DisbursementController;
use App\Modules\Loans\Controllers\LoanAdminController;
use App\Modules\Loans\Controllers\LoanController;
use App\Modules\Marketplace\Controllers\MarketplaceController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Repayments\Controllers\RepaymentAdminController;
use App\Modules\Repayments\Controllers\RepaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| QuickShare REST API — Version 1
|--------------------------------------------------------------------------
|
| Base URL:  /api/v1
| Auth:      Bearer token (Laravel Sanctum)
| Format:    JSON
|
*/

// ─── Auth ────────────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {

    // Public — rate-limited to 10/min
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    // Authenticated
    Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/me', [AuthController::class, 'updateProfile'])->name('update-profile');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/tokens', [AuthController::class, 'tokens'])->name('tokens');
        Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken'])->name('tokens.revoke');
    });
});

// ─── KYC ─────────────────────────────────────────────────────────────────
Route::prefix('kyc')->name('kyc.')->middleware(['auth:sanctum', 'active_user'])->group(function () {

    // Borrower KYC
    Route::middleware('permission:submit_kyc')->group(function () {
        Route::get('/status', [KycController::class, 'status'])->name('status');
        Route::post('/submit', [KycController::class, 'submit'])->name('submit');
        Route::post('/{submission}/resubmit', [KycController::class, 'resubmit'])->name('resubmit');
        Route::get('/documents/{document}/download', [KycController::class, 'downloadDocument'])->name('document.download');
    });

    // Admin KYC review
    Route::middleware('permission:approve_kyc')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pending', [KycAdminController::class, 'pending'])->name('pending');
        Route::get('/{submission}', [KycAdminController::class, 'show'])->name('show');
        Route::post('/{submission}/review', [KycAdminController::class, 'review'])->name('review');
    });
});

// ─── Loans ───────────────────────────────────────────────────────────────
Route::prefix('loans')->name('loans.')->middleware(['auth:sanctum', 'active_user'])->group(function () {

    // Borrower: view + manage own loans
    Route::middleware('permission:view_own_loans')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::post('/{loan}/cancel', [LoanController::class, 'cancel'])->name('cancel');
        Route::get('/{loan}/disbursements', [DisbursementController::class, 'forLoan'])->name('disbursements');
    });

    // Borrower: request + calculate
    Route::middleware('permission:request_loan')->group(function () {
        Route::post('/request', [LoanController::class, 'store'])->name('request');
        Route::post('/calculate', [LoanController::class, 'calculate'])->name('calculate');
    });

    // Borrower: affordability
    Route::middleware('permission:request_loan')->prefix('affordability')->name('affordability.')->group(function () {
        Route::post('/assess', [AffordabilityController::class, 'assess'])->name('assess');
        Route::post('/max-loan', [AffordabilityController::class, 'maxLoan'])->name('max-loan');
        Route::get('/history', [AffordabilityController::class, 'history'])->name('history');
    });

    // Admin: loan management
    Route::middleware('permission:manage_loans')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pending', [LoanAdminController::class, 'index'])->name('pending');
        Route::get('/marketplace', [LoanAdminController::class, 'marketplace'])->name('marketplace');
        Route::get('/{loan}', [LoanAdminController::class, 'show'])->name('show');
        Route::post('/{loan}/approve', [LoanAdminController::class, 'approve'])->name('approve');
        Route::post('/{loan}/reject', [LoanAdminController::class, 'reject'])->name('reject');
    });

    // Admin: disbursements
    Route::middleware('permission:manage_loans')->prefix('disbursements')->name('disbursements.')->group(function () {
        Route::get('/pending', [DisbursementController::class, 'pending'])->name('pending');
        Route::get('/failed-retry', [DisbursementController::class, 'failedRetry'])->name('failed-retry');
        Route::get('/reconciliation-report', [DisbursementController::class, 'reconciliationReport'])->name('reconciliation');
        Route::get('/{disbursement}', [DisbursementController::class, 'show'])->name('show');
        Route::post('/{disbursement}/retry', [DisbursementController::class, 'retry'])->name('retry');
        Route::post('/{disbursement}/reconcile', [DisbursementController::class, 'reconcile'])->name('reconcile');
    });
});

// ─── Marketplace ─────────────────────────────────────────────────────────
Route::prefix('marketplace')->name('marketplace.')->middleware(['auth:sanctum', 'active_user', 'permission:view_marketplace'])->group(function () {
    Route::get('/', [MarketplaceController::class, 'index'])->name('index');
    Route::get('/stats', [MarketplaceController::class, 'stats'])->name('stats');
    Route::get('/{loan}', [MarketplaceController::class, 'show'])->name('show');
});

// ─── Funding ─────────────────────────────────────────────────────────────
Route::prefix('funding')->name('funding.')->middleware(['auth:sanctum', 'active_user'])->group(function () {

    // Lender: fund loans + portfolio
    Route::middleware('permission:fund_loan')->group(function () {
        Route::get('/', [FundingController::class, 'index'])->name('index');
        Route::post('/{loan}', [FundingController::class, 'store'])->name('fund');
        Route::get('/{fundingTransaction}', [FundingController::class, 'show'])->name('show');
        Route::post('/{fundingTransaction}/cancel', [FundingController::class, 'cancel'])->name('cancel');
        Route::get('/loan/{loan}/fundings', [FundingController::class, 'loanFundings'])->name('loan-fundings');
    });

    Route::middleware('permission:view_own_portfolio')->group(function () {
        Route::get('/portfolio/summary', [FundingController::class, 'portfolio'])->name('portfolio');
    });
});

// ─── Repayments ──────────────────────────────────────────────────────────
Route::prefix('repayments')->name('repayments.')->middleware(['auth:sanctum', 'active_user'])->group(function () {

    // Borrower: view + make repayments
    Route::middleware('permission:make_repayment')->group(function () {
        Route::get('/', [RepaymentController::class, 'index'])->name('index');
        Route::post('/', [RepaymentController::class, 'store'])->name('store');
        Route::get('/schedule/{loan}', [RepaymentController::class, 'schedule'])->name('schedule');
        Route::get('/{repayment}', [RepaymentController::class, 'show'])->name('show');
    });

    // Lender: view earnings
    Route::middleware('permission:view_lender_earnings')->group(function () {
        Route::get('/lender/earnings', [RepaymentController::class, 'lenderEarnings'])->name('lender.earnings');
        Route::get('/lender/summary', [RepaymentController::class, 'lenderSummary'])->name('lender.summary');
    });

    // Admin: repayment management
    Route::middleware('permission:manage_repayments')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [RepaymentAdminController::class, 'index'])->name('index');
        Route::get('/overdue/summary', [RepaymentAdminController::class, 'overdueSummary'])->name('overdue-summary');
        Route::get('/upcoming', [RepaymentAdminController::class, 'upcoming'])->name('upcoming');
        Route::post('/check-overdue', [RepaymentAdminController::class, 'triggerOverdueCheck'])->name('check-overdue');
        Route::get('/{repayment}', [RepaymentAdminController::class, 'show'])->name('show');
        Route::post('/{repayment}/mark-defaulted', [RepaymentAdminController::class, 'markDefaulted'])->name('mark-defaulted');
        Route::post('/{repayment}/waive-penalty', [RepaymentAdminController::class, 'waivePenalty'])->name('waive-penalty');
    });
});

// ─── Notifications ───────────────────────────────────────────────────────
Route::prefix('notifications')->name('notifications.')->middleware(['auth:sanctum', 'active_user'])->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
    Route::get('/count', [NotificationController::class, 'count'])->name('count');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
});

// ─── Webhooks ──────────────────────────────────────────────────────────
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // Mifos X webhook — verify secret signature
    Route::post('/mifos', MifosWebhookController::class)->name('mifos');
});
