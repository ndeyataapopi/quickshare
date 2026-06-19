<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Loans\Events\LoanApproved;
use App\Modules\Loans\Events\LoanDisbursed;
use App\Modules\Loans\Events\LoanRejected;
use App\Modules\Loans\Jobs\SyncLoanToExternalJob;
use Illuminate\Support\Facades\Config;

class SyncLoanToExternalProvider
{
    public function handle(object $event): void
    {
        if (! Config::get('mifos.enabled') || ! Config::get('mifos.sync.auto_push_loan')) {
            return;
        }

        if ($event instanceof LoanApproved) {
            SyncLoanToExternalJob::dispatch($event->loanId, 'approve');
        } elseif ($event instanceof LoanRejected) {
            SyncLoanToExternalJob::dispatch($event->loanId, 'reject');
        } elseif ($event instanceof LoanDisbursed) {
            SyncLoanToExternalJob::dispatch($event->loanId, 'disburse');
        }
    }
}
