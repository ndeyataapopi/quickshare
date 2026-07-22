<?php

use App\Http\Controllers\Admin\DisbursementController;
use App\Http\Controllers\Borrower\LoanController;
use App\Http\Controllers\Borrower\RepaymentController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\FundingController;
use App\Http\Controllers\KYCController;
use App\Http\Controllers\Lender\AnalyticsController;
use App\Http\Controllers\Lender\EarningsController;
use App\Http\Controllers\Lender\InvestmentController;
use App\Http\Controllers\Lender\MarketplaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\TrustScoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:client', 'active_user'])->prefix('client')->name('client.')->group(function () {

    // ─── Dashboard ────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── KYC ──────────────────────────────────────────────────────────
    Route::prefix('kyc')->name('kyc.')->group(function () {
        Route::get('/', [KYCController::class, 'upload'])->name('upload');
        Route::post('/', [KYCController::class, 'store'])->name('store');
    });

    // ─── Loans (Borrower) ─────────────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/create', [LoanController::class, 'create'])->name('create');
        Route::get('/agreement-preview', [LoanController::class, 'agreementPreview'])->name('agreement-preview');
        Route::post('/', [LoanController::class, 'store'])->name('store');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::delete('/{loan}', [LoanController::class, 'cancel'])->name('cancel');
    });

    // Borrower disbursement confirmation (outside kyc_verified - loan already approved)
    Route::post('/loans/{loan}/confirm-disbursement', [DisbursementController::class, 'borrowerConfirm'])->name('loans.disbursement.confirm');
    Route::post('/loans/{loan}/reject-disbursement', [DisbursementController::class, 'borrowerReject'])->name('loans.disbursement.reject');

    // ─── Repayments (Borrower) ────────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('repayments')->name('repayments.')->group(function () {
        Route::get('/', [RepaymentController::class, 'index'])->name('index');
        Route::get('/create', [RepaymentController::class, 'create'])->name('create');
        Route::post('/', [RepaymentController::class, 'store'])->name('store');
        Route::get('/{repayment}', [RepaymentController::class, 'show'])->name('show');
    });

    // ─── Marketplace (Lender) ─────────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('marketplace')->name('marketplace.')->group(function () {
        Route::get('/', [MarketplaceController::class, 'index'])->name('index');
        Route::get('/{loan}', [MarketplaceController::class, 'show'])->name('show');
        Route::post('/{loan}/fund', [MarketplaceController::class, 'fund'])->name('fund');
    });

    // ─── Funding / Escrow (Lender) ────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('funding')->name('funding.')->group(function () {
        Route::get('/{transaction}', [FundingController::class, 'show'])->name('show');
        Route::get('/{transaction}/payment', [FundingController::class, 'payment'])->name('payment');
        Route::post('/{transaction}/payment', [FundingController::class, 'submitPayment'])->name('payment.submit');
    });

    // ─── Investments (Lender) ─────────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('investments')->name('investments.')->group(function () {
        Route::get('/', [InvestmentController::class, 'index'])->name('index');
        Route::get('/{investment}', [InvestmentController::class, 'show'])->name('show');
    });

    // ─── Earnings (Lender) ────────────────────────────────────────────
    Route::middleware(['kyc_verified'])->prefix('earnings')->name('earnings.')->group(function () {
        Route::get('/', [EarningsController::class, 'index'])->name('index');
    });

    // ─── Analytics ────────────────────────────────────────────────────
    Route::middleware(['kyc_verified'])->get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // ─── Trust Score ──────────────────────────────────────────────────
    Route::get('/trust-score', [TrustScoreController::class, 'index'])->name('trust-score.index');

    // ─── Notifications ────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    });

    // ─── Referrals ────────────────────────────────────────────────────
    Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');

    // ─── Profile ──────────────────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->middleware('prevent_impersonation')->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->middleware('prevent_impersonation')->name('destroy');
    });
});
