<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// ─── Public Routes ────────────────────────────────────────────────────────────
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/how-it-works', [PublicController::class, 'howItWorks'])->name('how-it-works');
Route::get('/faq', [PublicController::class, 'faq'])->name('faq');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/privacy-policy', [PublicController::class, 'privacy'])->name('privacy');
Route::get('/terms-and-conditions', [PublicController::class, 'terms'])->name('terms');

// ─── Generic Dashboard Redirect (role-based) ──────────────────────────────────
Route::get('/dashboard', function () {
    if (auth()->check()) {
        if (auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('client.dashboard');
    }
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

// ─── Health & Monitoring ──────────────────────────────────────────────────────
Route::get('/health', HealthController::class)->name('health');
Route::get('/monitoring', MonitoringController::class)->name('monitoring');

// ─── Auth, Admin & Client Route Files ────────────────────────────────────────
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/client.php';
