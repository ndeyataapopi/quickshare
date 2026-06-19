<?php

namespace App\Modules\Marketplace\Listeners;

use App\Models\ActivityLog;
use App\Modules\Marketplace\Events\LoanListed;
use App\Modules\Marketplace\Events\LoanDelisted;

class LogMarketplaceActivity
{
    public function handle(LoanListed|LoanDelisted $event): void
    {
        $action = $event instanceof LoanListed ? 'marketplace.listed' : 'marketplace.delisted';
        $description = $event instanceof LoanListed
            ? "Loan #{$event->loanId} listed on marketplace"
            : "Loan #{$event->loanId} delisted: {$event->reason}";

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => ['loan_id' => $event->loanId],
            'ip_address' => request()->ip(),
        ]);
    }
}
