<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\KYCController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\FundingController;
use App\Http\Controllers\Admin\DisbursementController;
use App\Http\Controllers\Admin\RepaymentController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\FraudController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // ─── Dashboard ────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');

    // ─── Users ────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::patch('/{user}/status', [UserController::class, 'updateStatus'])->name('status');
    });

    // ─── KYC ──────────────────────────────────────────────────────────
    Route::prefix('kyc')->name('kyc.')->group(function () {
        Route::get('/', [KYCController::class, 'index'])->name('index');
        Route::get('/document/{document}', [KYCController::class, 'viewDocument'])->name('document');
        Route::get('/{submission}', [KYCController::class, 'show'])->name('show');
        Route::put('/{submission}', [KYCController::class, 'update'])->name('update');
    });

    // ─── Loans ────────────────────────────────────────────────────────
    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::put('/{loan}', [LoanController::class, 'update'])->name('update');

        Route::get('/{loan}/agreement', [LoanController::class, 'agreement'])->name('agreement');
        Route::get('/{loan}/agreement/pdf', [LoanController::class, 'pdf'])->name('agreement.pdf');
        Route::get('/{loan}/agreement/download', [LoanController::class, 'download'])->name('agreement.download');
        Route::post('/{loan}/agreement/resend', [LoanController::class, 'resend'])->name('agreement.resend');
    });

    // ─── Funding (Escrow) ─────────────────────────────────────────────
    Route::prefix('funding')->name('funding.')->group(function () {
        Route::get('/', [FundingController::class, 'index'])->name('index');
        Route::get('/{transaction}', [FundingController::class, 'show'])->name('show');
        Route::patch('/{transaction}/confirm', [FundingController::class, 'confirm'])->name('confirm');
        Route::patch('/{transaction}/cancel', [FundingController::class, 'cancel'])->name('cancel');
    });

    // ─── Disbursements ────────────────────────────────────────────────
    Route::prefix('disbursements')->name('disbursements.')->group(function () {
        Route::get('/', [DisbursementController::class, 'index'])->name('index');
        Route::get('/{loan}', [DisbursementController::class, 'show'])->name('show');
        Route::post('/{loan}/disburse', [DisbursementController::class, 'disburse'])->name('disburse');
        Route::patch('/{loan}/confirm', [DisbursementController::class, 'confirm'])->name('confirm');
    });

    // ─── Repayments ───────────────────────────────────────────────────
    Route::prefix('repayments')->name('repayments.')->group(function () {
        Route::get('/', [RepaymentController::class, 'index'])->name('index');
        Route::get('/{repayment}', [RepaymentController::class, 'show'])->name('show');
        Route::patch('/{repayment}/confirm', [RepaymentController::class, 'confirm'])->name('confirm');
    });

    // ─── Collections ──────────────────────────────────────────────────
    Route::prefix('collections')->name('collections.')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::get('/{case}', [CollectionController::class, 'show'])->name('show');
        Route::put('/{case}', [CollectionController::class, 'update'])->name('update');
    });

    // ─── Fraud Monitoring ─────────────────────────────────────────────
    Route::prefix('fraud')->name('fraud.')->group(function () {
        Route::get('/', [FraudController::class, 'index'])->name('index');
        Route::get('/{alert}', [FraudController::class, 'show'])->name('show');
        Route::put('/{alert}', [FraudController::class, 'update'])->name('update');
    });

    // ─── Audit Logs ───────────────────────────────────────────────────
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditController::class, 'index'])->name('index');
    });

    // ─── Reports ──────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/{type}', [ReportController::class, 'show'])->name('show');
    });

    // ─── Settings ─────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'update'])->name('update');
    });
});
