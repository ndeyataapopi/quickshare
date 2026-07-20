<?php

namespace App\Modules\Loans;

use App\Modules\Loans\Events\ExternalLoanStatusUpdated;
use App\Modules\Loans\Events\LoanApproved;
use App\Modules\Loans\Events\LoanDisbursed;
use App\Modules\Loans\Events\LoanRejected;
use App\Modules\Loans\Events\LoanRequested;
use App\Modules\Loans\Listeners\LogLoanRequest;
use App\Modules\Loans\Listeners\NotifyLoanStatus;
use App\Modules\Loans\Listeners\NotifyLoanSubmitted;
use App\Modules\Loans\Listeners\ProcessLoanDisbursement;
use App\Modules\Loans\Listeners\SyncLoanToExternalProvider;
use App\Modules\Loans\Listeners\TriggerExternalStatusSync;
use App\Modules\Loans\Services\DisbursementService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoansServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DisbursementService::class);
    }

    public function boot(): void
    {
        Event::listen(LoanRequested::class, LogLoanRequest::class);
        Event::listen(LoanRequested::class, NotifyLoanSubmitted::class);
        Event::listen(LoanApproved::class, NotifyLoanStatus::class);
        Event::listen(LoanApproved::class, SyncLoanToExternalProvider::class);
        Event::listen(LoanRejected::class, NotifyLoanStatus::class);
        Event::listen(LoanRejected::class, SyncLoanToExternalProvider::class);
        Event::listen(LoanDisbursed::class, ProcessLoanDisbursement::class);
        Event::listen(LoanDisbursed::class, SyncLoanToExternalProvider::class);
        Event::listen(ExternalLoanStatusUpdated::class, TriggerExternalStatusSync::class);
    }
}
