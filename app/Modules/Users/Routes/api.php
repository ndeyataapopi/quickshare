<?php

use Illuminate\Support\Facades\Route;

// Any authenticated user: own profile
Route::middleware(['auth:sanctum', 'permission:view_own_profile'])->group(function () {
    // Route::get('/profile', [App\Modules\Users\Controllers\UserController::class, 'profile']);
    // Route::put('/profile', [App\Modules\Users\Controllers\UserController::class, 'updateProfile']);
});

// Admin/Compliance: manage users
Route::middleware(['auth:sanctum', 'permission:manage_users'])->group(function () {
    // Route::get('/', [App\Modules\Users\Controllers\UserAdminController::class, 'index']);
    // Route::get('/{user}', [App\Modules\Users\Controllers\UserAdminController::class, 'show']);
    // Route::put('/{user}/status', [App\Modules\Users\Controllers\UserAdminController::class, 'updateStatus']);
    // Route::put('/{user}/role', [App\Modules\Users\Controllers\UserAdminController::class, 'updateRole']);
});
