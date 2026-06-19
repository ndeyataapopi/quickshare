<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread', [NotificationController::class, 'unread']);
    Route::get('/count', [NotificationController::class, 'count']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/', [NotificationController::class, 'destroyAll']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});
