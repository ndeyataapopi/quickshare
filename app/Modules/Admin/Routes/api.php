<?php

use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Admin\Controllers\AdminUserController;
use App\Modules\Admin\Controllers\FraudController;
use Illuminate\Support\Facades\Route;

// Admin-only routes: Dashboard
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Main dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/overview', [AdminDashboardController::class, 'overview']);
    
    // Stats endpoints
    Route::get('/dashboard/kyc', [AdminDashboardController::class, 'kycStats']);
    Route::get('/dashboard/loans', [AdminDashboardController::class, 'loanStats']);
    Route::get('/dashboard/funding', [AdminDashboardController::class, 'fundingStats']);
    Route::get('/dashboard/repayments', [AdminDashboardController::class, 'repaymentStats']);
    Route::get('/dashboard/collections', [AdminDashboardController::class, 'collectionsStats']);
    Route::get('/dashboard/users', [AdminDashboardController::class, 'userStats']);
    Route::get('/dashboard/revenue', [AdminDashboardController::class, 'revenueStats']);
    
    // Charts and activity
    Route::get('/dashboard/charts', [AdminDashboardController::class, 'charts']);
    Route::get('/dashboard/recent-activity', [AdminDashboardController::class, 'recentActivity']);
});

// Admin: User Management
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users')->group(function () {
    Route::get('/', [AdminUserController::class, 'index']);
    Route::get('/{user}', [AdminUserController::class, 'show']);
    Route::post('/{user}/status', [AdminUserController::class, 'updateStatus']);
    Route::post('/{user}/role', [AdminUserController::class, 'updateRole']);
    Route::post('/{user}/trust-score', [AdminUserController::class, 'adjustTrustScore']);
    Route::post('/{user}/scan-fraud', [AdminUserController::class, 'scanFraud']);
    Route::get('/{user}/activity', [AdminUserController::class, 'activityLog']);
});

// Admin: Fraud Detection
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('fraud')->group(function () {
    Route::get('/summary', [FraudController::class, 'summary']);
    Route::get('/types', [FraudController::class, 'flagTypes']);
    Route::get('/queue', [FraudController::class, 'reviewQueue']);
    Route::post('/trigger-scan', [FraudController::class, 'triggerScan']);
    Route::get('/flags/{flag}', [FraudController::class, 'show']);
    Route::post('/flags/{flag}/under-review', [FraudController::class, 'markUnderReview']);
    Route::post('/flags/{flag}/confirm', [FraudController::class, 'confirm']);
    Route::post('/flags/{flag}/false-positive', [FraudController::class, 'markFalsePositive']);
    Route::post('/flags/{flag}/resolve', [FraudController::class, 'resolve']);
});
