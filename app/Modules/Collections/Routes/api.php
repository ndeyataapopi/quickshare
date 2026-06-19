<?php

use App\Modules\Collections\Controllers\CollectionsController;
use Illuminate\Support\Facades\Route;

// Admin: collections management
Route::middleware(['auth:sanctum', 'permission:manage_repayments'])->group(function () {
    Route::get('/dashboard', [CollectionsController::class, 'dashboard']);
    Route::get('/logs', [CollectionsController::class, 'logs']);
    Route::get('/loan/{loan}/history', [CollectionsController::class, 'loanHistory']);
    Route::get('/borrower/{borrower}/stats', [CollectionsController::class, 'borrowerStats']);
    Route::post('/trigger-reminders', [CollectionsController::class, 'triggerReminders']);
    Route::post('/process-escalations', [CollectionsController::class, 'processEscalations']);
    Route::post('/repayment/{repayment}/send-reminder', [CollectionsController::class, 'sendManualReminder']);
    Route::post('/loan/{loan}/process-default', [CollectionsController::class, 'processDefault']);
    Route::post('/logs/{log}/update-status', [CollectionsController::class, 'updateLogStatus']);
});
