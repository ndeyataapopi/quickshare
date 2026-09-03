<?php

use App\Modules\Payments\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{provider}', [WebhookController::class, 'handle'])
    ->name('payments.webhooks.handle');
