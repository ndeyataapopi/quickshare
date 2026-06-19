<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Loans\Events\ExternalLoanStatusUpdated;
use App\Modules\Loans\Jobs\SyncExternalLoanStatusJob;
use Illuminate\Support\Facades\Config;

class TriggerExternalStatusSync
{
    public function handle(ExternalLoanStatusUpdated $event): void
    {
        if (! Config::get('mifos.enabled') || ! Config::get('mifos.sync.auto_pull_status')) {
            return;
        }

        SyncExternalLoanStatusJob::dispatch($event->loanId);
    }
}
