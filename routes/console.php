<?php

use App\Modules\Loans\Jobs\ReconcileLoansJob;
use App\Modules\Repayments\Jobs\CheckOverdueRepaymentsJob;
use App\Modules\Repayments\Jobs\SendRepaymentRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send repayment reminders daily at 8am
Schedule::job(new SendRepaymentRemindersJob)->dailyAt('08:00')
    ->name('send-repayment-reminders')
    ->withoutOverlapping();

// Check for overdue repayments every 6 hours
Schedule::job(new CheckOverdueRepaymentsJob)->everySixHours()
    ->name('check-overdue-repayments')
    ->withoutOverlapping();

// Reconcile external loan status daily at 2am
Schedule::job(new ReconcileLoansJob)->dailyAt('02:00')
    ->name('reconcile-external-loans')
    ->withoutOverlapping();
